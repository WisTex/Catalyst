# Easy install setup script
    
## This installation script was provided by the community and is officially unsupported. Use at your own risk. Only use with a fresh install of Debian stable. If you have a server that has Apache, PHP, MySQL, SSL, etc. already installed this will break your server. 

Here you will find a quick and easy way to set up a website capable of joining the fediverse, using software from the Streams repository. All you have to do is run the setup script, enter some information and the magic will happen. Check the [INSTALL.md](INSTALL.md) file for step-by-step instructions.

## Requirements

Before you start, make sure you have the following:

- A computer/server running a freshly installed Debian GNU/Linux system , on which you can use a command line terminal.  It can be a mini-PC or a Raspberry Pi at home, a dedicated server or a VPS.
- A domain name pointing to this computer/server (a few Dynamic DNS providers can automatically be configured as you will read below). You can register a free subdomain with providers such as FreeDNS or NoIP, or buy a domain elsewhere.
- Ports 80 & 443 open on your firewall, forwarded to your computer/server you use an IPv4 internet connection through a router (i.e. your ISP router at home).

## What the setup script will do for you:

+ Install everything required by your website, basically a web server (Apache or Nginx), PHP, a database server (MariaDB/MySQL), certbot (to obtain Let’s Encrypt SSL certificates),
+ Create a database for your website
+ Run certbot to have a secure connection (http*s*)
+ Create a script for daily maintenance:
  - backup to external disk (certificates, database, /var/www/) - Optional
  - renew certfificate (Let’s Encrypt)
  - update of your website software (git)
  - update of your Debian operating system
  - restart your computer/server
+ Create cron jobs for
  - dynamic DNS (works with FreeDNS, Gandi or selfHOST) every 5 minutes
  - Run.php for your website every 10 minutes
  - daily maintenance script every day at 05:30

## Some more details

### Dynamic DNS configuration

If you plan to run your website on a computer at home, you may have to deal with the fact that your internet provider doesn’t offer a fixed IP address. The setup script has extensions for 3 Dynamic DNS (DDNS) providers, which can help you ensure that your domain name will point to your computer/server even if its IP address changes:

- FreeDNS (freedns.afraid.org) is a free of charge provider that offers free subdomains. You simply need to open an account there and create your subdomain (there’s plenty of domains you can choose from). Once your subdomain is created, you will need to find the update key you will use during install.

- Gandi.net is a french domain name registrar that has a nice API for DDNS (Gandi LiveDNS). If you buy a domain there, you can generate an API key for your account that can be used during install.

- selfHOST.de is a german (and german speaking only) registrar. If you have an account and buy a domain there, you will need to provide an ID & password to use the setup script’s DDNS configuration.

### USB drive backup

As of April 2022, this is the only automated backup system you can configure with the setup-script. It is only accessible if you use the manually edited config file method (hopefully it should soon be available in the beginner-friendly interface).

This only works if you have physical access to the machine where your website is running, you can not use this backup system with a distant computer/server. A solution for distant backups would certainly be a good idea, feel free to work on this if you have the skills to do so.

### Note on Rasperry Pi install

It is recommended to run the Raspi without graphical frontend. Use the following command to boot the Raspi in console mode only:

    sudo raspi-config

*Don’t forget to change the default password for user pi!*

