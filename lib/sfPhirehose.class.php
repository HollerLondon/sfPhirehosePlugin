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
    $md_phrases = Doctrine::getTable('TwitterSearchPhrase')
                ->createQuery('s')
                ->select('s.phrase AS phrase')
                ->execute(null,Doctrine::HYDRATE_SINGLE_SCALAR);
    
    if(!is_array($md_phrases))
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
    return sfConfig::get('app_phirehose_follow',array());
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
   * Takes a raw JSON-serialised Tweet from the Twitter Firehose and creates a 
   * new Doctrine Tweet object from it
   *
   * @return void
   * @author Ben Lancaster
   **/
  public function enqueueStatus($raw)
  {
    try
    {
      $data = json_decode($raw);
      
      // Delete tweets as requested by twitter
      if(isset($data->delete) && isset($data->delete->status))
      {
        Doctrine::getTable('Tweet')
          ->createQuery('t')
          ->delete()
          ->where('guid = ?',$data->delete->status->id_str)
          ->execute();
        $this->log("Deleted tweet with id %s",$data->delete->status->id_str);
      }

      // If we're consuming too much too quickly, Twitter will tell us
      elseif(isset($data->limit))
      {
        $this->log(sprintf("%u status(es) have been rate-limited"),
          $data->limit
        );
      }

      // Get rid of any geo data as requested by Twitter
      elseif(isset($data->scrub_geo))
      {
        Doctrine::getTable('Tweet')
          ->createQuery('t')
          ->update()
          ->set('t.latitude','NULL')
          ->set('t.longitude','NULL')
          ->where('t.twitter_user_id = ?',$data->scrub_geo->user_id)
          ->andWhere('t.latitude IS NOT NULL')
          ->execute();
        $this->log("Scrubbed geodata for user %s",$data->scrub_geo->user_id);
      }
      else
      {
        // Create a new Tweet object from the JSON data
        $tweet = Tweet::hydrateFromDecodedResponse($data);
        $tweet->save();
        $tweet->free(); // this helps minimise memory leakage.
      }
    }
    catch(Exception $e)
    {
      $this->task->logSection(get_class($e),
        sprintf("%s on line %u of %s",$e->getMessage(),$e->getLine(),$e->getFile())
      );
    }
  }

  public function log($msg)
  {
    $this->task->logSection('Phirehose',$msg);
    $this->task->logSection('Memory',
      sprintf("Usage: %uM (current), %uM (peak)",
        round(memory_get_usage() / 1024 / 1024),
        round(memory_get_peak_usage() / 1024 / 1024)
      )
    );
  }
}
