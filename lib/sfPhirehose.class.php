<?php
class sfPhirehose extends Phirehose
{
  public $task;
  
  private $phrases;
  
  /**
   * Overload and extend Phirehose's constructor to also hydrate the 
   * ResidentCollection and store it as an object property
   *
   * @param string $username Twitter API User
   * @param string $password Twitter API Password
   * @param const Phirehose::METHOD_*
   * @param const Phirehose::FORMAT_*
   * @return null
   **/
  public function __construct($username, $password, $method = Phirehose::METHOD_SAMPLE, $format = self::FORMAT_JSON)
  {
    $this->phrases = sfConfig::get('app_phirehose_track', array());
    return parent::__construct($username, $password, $method, $format);
  }

  /**
   * Get the search phrases to search against
   *
   * @return array
   * @author Ben Lancaster
   **/
  protected function getSearchPhrases()
  {
    // This'll give us a multidimensional array, which is well annoying
    $md_phrases = TwitterSearchPhraseTable::getInstance()
                ->createQuery('s')
                ->select('s.phrase AS phrase')
                ->execute(null,Doctrine::HYDRATE_SINGLE_SCALAR);
    
    if (!is_array($md_phrases))
    {
      $md_phrases = array($md_phrases);
    }
    
    return $md_phrases;
  }
  
  /**
   * Spits out an array of numeric twitter user ids of the users whose tweets
   * we'll be following
   *
   * @return array
   * @author Ben Lancaster
   **/
  protected function getFollows()
  {
    return sfConfig::get('app_phirehose_follow', array());
  }
  
  /**
   * Phirehose calls this method periodically, which sets the search terms to 
   * track and the users to follow from self::getFollows() and
   * self::getSearchPhrases()
   *
   * @return void
   * @see self::getSearchPhrases(), self:::getFollows()
   * @author Ben Lancaster
   **/
  public function checkFilterPredicates()
  {
    $this->log("Checking search terms and users to follow");

    $this->setTrack(
      array_unique(array_merge($this->getSearchPhrases(),$this->phrases))
    );

    $this->setFollow(
      array_unique(array_merge(sfConfig::get('app_phirehose_follow',array()),$this->getFollows()))
    );
  }

  /**
   * Takes a raw JSON-serialised Tweet from the Twitter Firehose and
   * sends it to Beanstalk to process
   *
   * @return void
   * @author Ben Lancaster
   **/
  public function enqueueStatus($raw)
  {
    $data = json_decode($raw, true);
    
    // If we're consuming too much too quickly, Twitter will tell us
    if (isset($data['limit']))
    {
      $this->log(sprintf("%u status(es) have been rate-limited"), $data['limit']['track']);
    }
    else
    {
      // Let beanstalk handle this work
      $pheanstalk = majaxPheanstalk::getInstance();
      $pheanstalk->useTube('tweets')->put($raw);
    }
  }

  public function log($msg)
  {
    $this->task->logSection('Phirehose', $msg);
    $this->task->logSection('Memory',
      sprintf("Usage: %uM (current), %uM (peak)",
        round(memory_get_usage() / 1024 / 1024),
        round(memory_get_peak_usage() / 1024 / 1024)
      )
    );
  }
}
