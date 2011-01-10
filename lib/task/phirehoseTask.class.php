<?php

class phirehoseTask extends sfBaseTask
{
  protected function configure()
  {
    // // add your own arguments here
    // $this->addArguments(array(
    //   new sfCommandArgument('my_arg', sfCommandArgument::REQUIRED, 'My argument'),
    // ));
    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The application name','backend'),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'dev'),
      new sfCommandOption('connection', null, sfCommandOption::PARAMETER_REQUIRED, 'The connection name', 'doctrine'),
      new sfCommandOption('daemonize', 'd', sfCommandOption::PARAMETER_NONE, 'Run in background', null)
    ));

    $this->namespace        = 'twitter';
    $this->name             = 'stream';
    $this->briefDescription = 'Grabs tweets from Twitter Streaming API and saves in database';
    $this->detailedDescription = <<<EOF
The [stream|INFO] task grabs tweets from Twitter Streaming API, and saves in the database.
Call it with:

  [php symfony twitter:stream|INFO]
EOF;
  }
  
  protected function execute($arguments = array(), $options = array())
  {
    if($options['daemonize'])
    {
      $this->logSection("Task","Attempting to Daemonise");
      $this->executeAsDaemon($arguments,$options);
      return;
    }
    $this->logSection("Task","Running in the foreground");
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
      $this->logSection('Daemon','Failed to fork off');
    }
    elseif ($pid)
    {
      $this->logSection('Daemon',sprintf("Spawned new stream process with pid %u",$pid));
      exit;
    }
    else 
    { // we are the child
      $this->doStuff($arguments,$options);
    }
    // detatch from the controlling terminal
    if (posix_setsid() == -1)
    {
      $this->logSection('Daemon','Could not detatch');
    }
    // setup signal handlers
    pcntl_signal(SIGTERM, "sig_handler");
    function sig_handler($signo) { if($signo == SIGTERM) exit(); }
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
    $connection = $databaseManager->getDatabase($options['connection'])->getConnection();
    
    $class_name = sfConfig::get('app_phirehose_class','sfPhirehose');

    $this->logSection("DB","Established");
    
    $this->logSection("Phirehose","Attempting to create Streaming API connection");
    $this->logSection("Phirehose",sprintf("Connecting as %s using class %s",sfConfig::get('app_phirehose_username'),$class_name));

    $searcher = new $class_name(
      sfConfig::get('app_phirehose_username'),
      sfConfig::get('app_phirehose_password'),
      Phirehose::METHOD_FILTER
    );
    
    $this->logSection("Phirehose","Streaming API connection established");

    $searcher->task = $this;
    $searcher->consume();
  }
  /**
   * Filters all Phirehose logs and puts them in the database.
   * @see parent::logSection
   */
  public function logSection($section, $message, $size = null, $style = 'INFO')
  {
    if($section == 'Phirehose' && sfConfig::get('app_phirehose_log_purge',86400) > 0)
    {
      $l = new TaskLog;
      $l['message'] = $message;
      $l->save();
      $l->free(true);
      
      $q = Doctrine::getTable('TaskLog')
        ->createQuery('t')
        ->delete()
        ->where('t.created_at < ?',date('Y-m-d H:i:s',strtotime(sprintf("-%u second",sfConfig::get('app_phirehose_log_purge',86400)))));
      
      parent::logSection("debug","Purging logs older than ".date('Y-m-d H:i:s',strtotime(sprintf("-%u second",sfConfig::get('app_phirehose_log_purge',86400)))));
      $q->execute();
      
    }
    return parent::logSection($section,$message,$size,$style);
  }
}
