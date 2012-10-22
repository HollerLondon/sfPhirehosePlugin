<?php

class phirehoseTask extends sfBaseTask
{
  protected function configure()
  {
    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The application name','backend'),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'dev'),
      new sfCommandOption('connection', null, sfCommandOption::PARAMETER_REQUIRED, 'The connection name', 'doctrine'),
      new sfCommandOption('daemonize', 'd', sfCommandOption::PARAMETER_NONE, 'Run in background', null)
    ));

    $this->namespace        = 'twitter';
    $this->name             = 'stream';
    $this->briefDescription = 'Grabs tweets from Twitter Streaming API, passes them to beanstalk and saves in database';
    $this->detailedDescription = <<<EOF
The [stream|INFO] task grabs tweets from Twitter Streaming API, passes them to beanstalk and saves in the database.
Call it with:

  [php symfony twitter:stream|INFO]
EOF;
  }
  
  protected function execute($arguments = array(), $options = array())
  {
    if ($options['daemonize'])
    {
      $this->logSection("Task", "Attempting to Daemonise");
      $this->executeAsDaemon($arguments,$options);
      return;
    }
    
    $this->logSection("Task", "Running in the foreground");
    $this->doStuff($arguments,$options);
  }

  /**
   * Executes task in the background using pcntl
   *
   * @return void
   */
  protected function executeAsDaemon($arguments = array(), $options = array())
  {
    // tick use required as of PHP 4.3.0
    declare(ticks = 1);

    $pid = pcntl_fork();
    if ($pid == -1)
    {
      $this->logSection('Daemon', 'Failed to fork off');
    }
    else if ($pid)
    {
      $this->logSection('Daemon', sprintf("Spawned new stream process with pid %u", $pid));
      exit;
    }
    else 
    { // we are the child
      $this->doStuff($arguments,$options);
    }
    
    // detatch from the controlling terminal
    if (posix_setsid() == -1)
    {
      $this->logSection('Daemon', 'Could not detatch');
    }
    
    // setup signal handlers
    pcntl_signal(SIGTERM, "sig_handler");
    
    function sig_handler($signo) { if ($signo == SIGTERM) exit(); }
  }

  /**
   * Logic of this task
   * Can be run as daemon or as normal script
   * @return void
   */
  private function doStuff($arguments = array(), $options = array())
  {
    $this->logSection("DB","Establishing connection...");

    // initialize the database connection
    $databaseManager = new sfDatabaseManager($this->configuration);
    $connection      = $databaseManager->getDatabase($options['connection'])->getConnection();
    
    // This task doesn't actually do a whole lot. It's this class that really
    // does the magic. The sfOauthPhirehose class is an extension of the core
    // OauthPhirehose task. If you want to add your own logic for:
    // - Fetching search terms
    // - Fetching users to follow
    // - Storing tweets
    // ... then this is the class to overload
    $class_name = sfConfig::get('app_phirehose_class', 'sfOauthPhirehose');
    
    // If we're using the old basic auth, lets not break the implementation
    // Degrade to using deprecated class
    if (is_null(sfConfig::get('app_phirehose_consumer_key')) && 'sfOauthPhirehose' == $class_name)
    {
      $class_name = 'sfPhirehose';
    }

    $this->logSection("DB", "Established");
    $this->logSection("Phirehose", "Attempting to create Streaming API connection");
    $this->logSection("Phirehose", sprintf("Connecting %s using class %s", 
                                            (is_null(sfConfig::get('app_phirehose_username')) ? 'with oauth' : 'as ' . sfConfig::get('app_phirehose_username')), 
                                            $class_name));

    $searcher = new $class_name(
      sfConfig::get('app_phirehose_access_token', sfConfig::get('app_phirehose_username')),         # Username is access token for oauth
      sfConfig::get('app_phirehose_access_token_secret', sfConfig::get('app_phirehose_password')),  # Password is the secret for oauth
      Phirehose::METHOD_FILTER
    );
    
    $this->logSection("Phirehose", "Streaming API connection established");
    $this->logSection('Phirehose', 'Using tube: '.sfConfig::get('app_phirehose_tube', 'tweets'));

    // We pass the task back in to the sfPhirehose task so we can log stuff
    // from it and have it logged by the task. That sounds cyclical and probably
    // doesn't make a lot of sense.
    $searcher->task = $this;
    $searcher->consume();
  }
  /**
   * Filters all Phirehose logs and puts them in the database.
   * @see parent::logSection
   */
  public function logSection($section, $message, $size = null, $style = 'INFO')
  {
    if ($section == 'Phirehose' && sfConfig::get('app_phirehose_log_purge', 86400) > 0)
    {
      $l            = new TaskLog;
      $l['message'] = $message;
      $l->save();
      $l->free(true);
      
      $q = TaskLogTable::getInstance()
                          ->createQuery('t')
                          ->delete()
                          ->where('t.created_at < ?', date('Y-m-d H:i:s', strtotime(sprintf("-%u second", sfConfig::get('app_phirehose_log_purge', 86400)))));
      
      parent::logSection("debug", "Purging logs older than " . date('Y-m-d H:i:s', strtotime(sprintf("-%u second", sfConfig::get('app_phirehose_log_purge', 86400)))));
      
      $q->execute();
    }
    
    return parent::logSection($section, $message, $size, $style);
  }
}
