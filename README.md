# always-forge
**AlwaysForge** is PHP forging fail-over for **Lisk** cryptocurrency. It will monitor all your nodes in real-time and switch forging to best server available. It uses active (maybe a little too aggressive) approach and best practices.

##Version:
`1.0.0`

##Dependencies:
Script require **PHP** with **cURL** support and **Cron**. If you want to run it on hosting instead of VPS - one with **SLA 99.99%** is highly recommended.

##Installation:
**Remember to add your monitor's server IP to lisk whitelist (for API and forging)!**

```
git clone https://github.com/4miners/always-forge
cd always-forge
cp config.json.example config.json
```
Edit `config.json` to your needs:
```
{
    "log_level": "info", // Log details level: debug, info, node
    "check_interval_sec": 1, // Checker will pause for that interval each loop
    "timeouts": {
        "request_sec": 3, // Timeout for cURL request, must be higher than connect_msec
        "connect_msec": 1000 // Timeout for cURL connection estabilisment
    },
    "delegate": {
        "address": "delegate_address", // Your delegate address
        "publicKey": "delegate_publicKey", // Your delegate public key
        "secret": "delegate_secret" // Your delegate secret
    },
    // List of servers, first server will have highest priority, last - lowest priority
    // Each server must have unique name! For your delegate security HTTPS connection is forced!
    "servers": [
        {
            "name": "mainnet-1 #1",
            "ip": "127.0.0.1",
            "port": 8000
        },
        {
            "name": "mainnet-1 #2",
            "ip": "127.0.0.1",
            "port": 8001
        }
    ]
}
```

Save config and test it:
```
php always-forge.php
```
If it works - add to your crontab `monitor_always_forge.sh` to run every minute, for example: `crontab -e`, then insert:
```
* * * * * bash /home/lisk/always-forge/monitor_always_forge.sh
```

##Enjoy increase of your delegate productivity. :)
Donation address: `16010222169256538112L`
