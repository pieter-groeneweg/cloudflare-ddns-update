# cloudflare-ddns-update
Update dynamic ip on (sub) domains using Cloudflare DNS

Do you have your own server stashed in your closet at home? You want it to be accessible from the outside all the time? You don't have a static IP?
There is no need to turn to (paid) ddns services.

Find your public dynamic IP (ipify.org).
Get yourself an account on cloudflare and setup your DNS with your IP address.

Setup an environment running PHP with curl enabled.
Copy the files and complete the config.php with your login, key and (sub)domains.
Setup a cron job that calls the index.php. The schedule depends on your own requirement.

That's it!

Now each time this updater runs, it checks your public ip, matches that with the one stored in stored.ip. If it does not match, it will update the stored.ip file and the DNS rules in cloudflare on the (sub)sdomains you specified in config.php.
