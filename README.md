sfPhirehosePlugin
=================

Twitter phirehose stream plugin with Beanstalk worker.

Externals
---------
on lib/vendor:

    pheanstalk      https://github.com/pda/pheanstalk.git/trunk
    phirehose       https://github.com/fennb/phirehose.git/trunk

on plugins:

    majaxPheanstalkPlugin https://github.com/benlancaster/majaxPheanstalkPlugin.git/trunk

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
       username:     # from twitter
       password:
       track:        [] # keywords to track - or use the DB table TwitterSearchPhrase
       follow:       [] # users to follow
        
To run
------

     ./symfony pheanstalk:run_worker frontend TweetWorker log/beanstalk.log
     ./symfony twitter:stream
     
Run streaming task as daemon

      ./symfony twitter:stream -d
