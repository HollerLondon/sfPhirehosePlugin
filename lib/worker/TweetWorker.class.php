<?php
/**
 * Takes JSON payload data from the Twitter Streaming API, processes it and
 * creates new Doctrine_Records for each payload
 *
 * @package dashboard
 * @subpackage twitter
 * @author Ben Lancaster
 * @author Jo Carter
 */
class TweetWorker extends majaxPheanstalkWorkerThread
{
  public function doRun()
  {
    $this->log('Tube: ' . sfConfig::get('app_phirehose_tube', 'tweets'));
    $job = $this->getJob(sfConfig::get('app_phirehose_tube', 'tweets'));
    
    if (!$job)
    {
      var_dump($job);
      return;
    }
    
    $decoded = json_decode($job->getData());
    
    if (is_null($decoded))
    {
      $this->log(var_export($job->getData()));
      majaxPheanstalk::deleteJob($job);
      
      return;
    }
    
    $payload = object_to_array($decoded);

    if (array_key_exists('delete', $payload))
    {
      $this->processDelete($payload);
    }
    else if (array_key_exists('scrub_geo', $payload))
    {
      $this->processScrubGeo();
    }
    else if (array_key_exists('retweeted_status', $payload))
    {
      $this->processRetweet($payload);
    }
    else
    {
      $this->processTweet($payload);
    }

    majaxPheanstalk::deleteJob($job);
    return;
  }
  
  /**
   * Delete any tweets as requested by Twitter 
   * 
   * @param array $payload Decoded JSON payload from Twitter
   */
  public function processDelete($payload)
  {
    TweetTable::getInstance()
              ->createQuery('t')
              ->delete()
              ->where('id = ?', $payload['delete']['status']['id_str'])
              ->execute();
  }
  
  /**
   * Delete any geo data as requested by Twitter 
   * 
   * @param array $payload Decoded JSON payload from Twitter
   */
  public function processScrubGeo($payload)
  {
    $q = TweetTable::getInstance()
                    ->createQuery('t')
                    ->update()
                    ->set('t.place_id', 'NULL')
                    ->where('t.twitter_user_id = ?', $payload['scrub_geo']['user_id'])
                    ->andWhere('t.place_id IS NOT NULL');

    if (isset($payload['scrub_geo']['up_to_status_id']))
    {
      $q->where('id <= ?', $payload['scrub_geo']['up_to_status_id']);
    }
                
    $q->execute();
  }

  /**
   * Process a retweet - just create a relationship between the user and the
   * original tweet
   *
   * @param array $payload Decoded JSON payload from Twitter
   */
  public function processRetweet($payload)
  {
    $original = TweetTable::getInstance()->findOneById($payload['retweeted_status']['id_str']) ?: new Tweet();

    // From array is smart enough to handle the complex JSON data
    if ($original->isNew())
    {
      $original->fromArray($payload['retweeted_status']);
    }

    $retweeter = TwitterUserSkeletonTable::getInstance()->findOneById($payload['user']['id']) ?: new TwitterUserSkeleton();
    
    if ($retweeter->isNew())
    {
      $retweeter->fromArray($payload['user']);
    }

    $original->RetweetedBy->add($retweeter);
    $original->save();
  }

  /**
   * Hydrate and process a regular tweet.
   *
   * @param array $payload Decoded JSON payload from Twitter
   */
  public function processTweet($payload, $tweet = null, $tries = 0)
  {
    $tweet = $tweet ?: new Tweet();

    // There's a chance we may already have a tweet with this ID.
    // For example, if someone replies to a tweet, but the streaming API
    // doesn't give us the original tweet this is in reply to until
    // after we get the reply
    // So, we have to try and save it, if it already exists, we
    // assign the identifier and refresh it
    try
    {
      $tweet->fromArray($payload);
      $tweet->save();
    }
    catch (Doctrine_Connection_Exception $e)
    {
      if ($e->getPortableCode() === Doctrine_Core::ERR_ALREADY_EXISTS)
      {
        $tries++;

        if ($tries < 2)
        {
          $tweet->fromArray($payload);
          $tweet->assignIdentifier($payload['id_str']);
          $tweet->save();
          return;
        }
      }
      throw $e;
    }
    catch (Exception $e)
    {
      $this->log($e->getMessage());
      $this->log($e->getTraceAsString());
    }
  }
}