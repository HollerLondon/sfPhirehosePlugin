sfPhirehosePlugin
=================

Twitter phirehose stream plugin with Beanstalk worker.

Externals
---------
on lib/vendor:

    pheanstalk      https://github.com/pda/pheanstalk.git/trunk
    phirehose       https://github.com/fennb/phirehose.git/trunk

on plugins:

    majaxPheanstalkPlugin https://github.com/HollerLondon/majaxPheanstalkPlugin.git/trunk

Global config
-------------

In app.yml

    pheanstalk:
      host:       127.0.0.1
      port:       11300
      # Set this if you've moved your pheanstalk library path
      path:       %SF_LIB_DIR%/vendor/pheanstalk
      
Requires autoload.yml

    autoload:
      phirehose:
        name:       phirehose
        path:       %SF_LIB_DIR%/vendor/phirehose/lib
        
Configuration
-------------

     phirehose:
       class:           myPhirehoseClass  # optional, must extend sfOauthPhirehose
       consumer_key:    ~                 # from your twitter application
       consumer_secret: ~
       access_token:    ~                 # from the twitter application generate your own access token and secret
       access_token_secret: ~
       track:           []                # keywords to track - or use the DB table TwitterSearchPhrase (or custom implementation)
       follow:          []                # users to follow
        
To run
------

Run tasks (ideally via supervisor):

     ./symfony pheanstalk:run_worker TweetWorker
     ./symfony twitter:stream
     
Run streaming task as daemon

      ./symfony twitter:stream -d
