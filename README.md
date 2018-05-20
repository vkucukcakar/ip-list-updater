# ip-list-updater

Automatic CDN and bogon IP list updater for firewall and server configurations

* Downloads IP lists to update firewall and server configurations
* Downloads bogon IP lists to blacklist in firewalls
* Downloads CDN/trusted proxy/reverse proxy IP ranges to whitelist in firewalls, update server configurations
* Supports Ipset/Iptables mode, Nginx ngx_http_realip_module, Apache mod_remoteip module, raw mode (for any firewall, server or daemon)
* Supports many input files with "IP address/netmask" format including raw IP lists, jsonp, xml, etc...
* Downloads multiple lists and merge
* IP address and list validation just in case
* Compatible with any daemon, server or firewall
* Pre-defined bogon IP list sources with keywords: spamhaus, cymru
* Pre-defined CDN IP range sources with keywords: cloudflare, cloudfront, fastly, maxcdn

## Requirements

* PHP-CLI with openssl extension

## Installation

* Install PHP-CLI with openssl extension if not installed (OS dependent)
	
* Install ip-list-updater.php to an appropriate location and give execute permission

	$ cd /usr/local/src/

	$ git clone https://github.com/vkucukcakar/ip-list-updater.git	

	$ cp ip-list-updater/ip-list-updater.php /usr/local/bin/
	
* Give execute permission if not cloned from github

	$ chmod +x /usr/local/bin/ip-list-updater.php
	

## Usage

Usage: ip-list-updater.php [OPTIONS]

Available options:

-u, --update                          *: Download IP lists and update the configuration files

-f, --force                            : Force update

-m, --mode                            *: Operation mode (raw*|ipset|nginx|apache)"

-c <command>, --success=<command>      : Success command to execute after a successful update"

-t <seconds>, --timeout=<seconds>      : Set download timeout

-n, --nocert                           : No certificate check

-x, --ipv                              : Validate IP version (4|6|all*)

-o <filename>, --output=<filename>    *: Write IP list to output file (old file will be overwritten)

-e <setname> --setname=<setname>       : Add IP list to ipset with setname (old list will be removed)

--ipset_path=<path>                    : Change default Ipset path
				
-s <urls>, --sources=<urls>           *: Set download sources ("spamhaus", "cymru", "cloudflare", "maxcdn", "cloudfront", "fastly" keywords or space separated custom URLs)
 
-v, --version                          : Display version and license information

-h, --help,                            : Display help

 
## Examples

### Examples (raw mode)

Short command syntax usage.

	$ ip-list-updater.php -u -m raw -x 4 -o "/etc/ip-list-updater.txt" -s "cloudflare" -c "/etc/myscript.sh"
	
Long command syntax usage.
	
	$ ip-list-updater.php --update --mode="raw" --ipv=4 --output="/etc/ip-list-updater.txt" --sources="https://www.cloudflare.com/ips-v4" --success="/etc/myscript.sh"

	
Doing some magic with bash and raw list. /etc/myscript.sh contents:	
	
	#!/usr/bin/env bash
	for IP in $(cat /etc/ip-list-updater.txt); do
		echo $IP
	done
	
### Examples (ipset mode)

This example demonstrates how to whitelist your CDN/reverse proxy IP range through ipset and iptables.

Create a proxylist set, create iptables rule to accept proxylist set for http/https ports, add Cloudflare IPv4 range to proxylist set.

	$ ipset create proxylist hash:net family inet hashsize 1024 maxelem 131072
	$ iptables -I INPUT -p tcp -m multiport --dports 80,443 -m set --match-set proxylist src -j ACCEPT
	$ ip-list-updater.php --update --mode="ipset" --setname="proxylist" --ipv=4 --output="/etc/proxylist.txt" --sources="cloudflare"

This example demonstrates how to block a bogonlist through ipset and iptables.

Create a bogonlist set, create iptables rule to drop bogonlist set, add Spamhaus IPv4 list to bogonlist set. 	
	
	$ ipset create bogonlist hash:net family inet hashsize 1024 maxelem 131072
	$ iptables -I INPUT -m set --match-set bogonlist src -j DROP
	$ ip-list-updater.php --update --mode="ipset" --setname="bogonlist" --ipv=4 --output="/etc/bogonlist.txt" --sources="spamhaus"

### Examples (nginx mode)

This example demonstrates how to make Nginx show correct connnecting IP via ngx_http_realip_module on a reverse proxy/CDN setup. 

Add the following to Nginx main configuration file.

	#real_ip_header X-Real-IP;
	#real_ip_header X-Forwarded-For;
	real_ip_header CF-Connecting-IP;
	include /etc/nginx-cloudflare.conf;

Update ip list and create Nginx (module ngx_http_realip_module) configuration file to be included. 
Success command will make Nginx reload configuration files without interruption. Make sure nginx path is correct at the success command.

	$ ip-list-updater.php --update --mode="nginx" --ipv=4 --output="/etc/nginx-cloudflare.conf" --sources="cloudflare" --success="/usr/bin/nginx -s reload"

For Cloudflare, both CF-Connecting-IP and X-Forwarded-For can be used. Please refer to your CDN's documentation for the correct header.
	
### Examples (apache mode)

This example demonstrates how to make Apache show correct connnecting IP via mod_remoteip on a reverse proxy/CDN setup. 

Modify the relevant section in Apache configuration file.

	<IfModule mod_remoteip.c>
		#RemoteIPHeader X-Forwarded-For
		RemoteIPHeader CF-Connecting-IP
		RemoteIPInternalProxyList /etc/apache-cloudflare.lst
	</IfModule>

Update ip list and create Apache (module mod_remoteip) trusted proxy list file to be included. 
Make sure Apache reload success command is correct which may be OS specific.

	$ ip-list-updater.php --update --mode="apache" --ipv=4 --output="/etc/apache-cloudflare.lst" --sources="cloudflare" --success="apachectl -k graceful"

### Examples (A real world example !!!)

In the following crontab entries, the first line downloads Spamhaus bogon IPv4 list daily at 03:15 AM, updates Ipset named "bogonlist", which is used by sptables (my firewall script), and only logs error output. [sptables](https://github.com/vkucukcakar/sptables)
The second line downloads the Cloudflare IPv4 range, updates Ipset named "proxylist", which is used by the firewall. (There should be another line if we had IPv6 set support as IPv4 sets are not compatible with IPv6 sets.)
The third line downloads the Cloudflare IP range, updates the server configuration and reloads Nginx with zero downtime by sending a HUP signal to the container by Docker.

15 3 * * * root /usr/local/bin/ip-list-updater.php --update --mode="ipset" --setname="bogonlist" --ipv=4 --output="/etc/bogonlist.txt" --sources="spamhaus" --success="ipset save bogonlist -f /etc/sptables/data/bogonlist.save" >/dev/null 2>/var/log/ip-list-updater.log
45 3 * * * root /usr/local/bin/ip-list-updater.php --update --mode="ipset" --setname="proxylist" --ipv=4 --output="/etc/proxylist.txt" --sources="cloudflare" --success="ipset save proxylist -f /etc/sptables/data/proxylist.save" >/dev/null 2>/var/log/ip-list-updater.log
30 3 * * * root /usr/local/bin/ip-list-updater.php --update --mode="nginx" --ipv=all --output="/lemp/configurations/cdn.conf" --sources="cloudflare" --success="docker kill --signal=HUP server-proxy" >/dev/null 2>/var/log/ip-list-updater.log

## Caveats

* In ipset mode, IPv4 and IPv6 sets have different structure and must be handled on separate lines with --ipv parameter.
* ip-list-updater is the successor of bogon-ip-updater, cf-ip-updater and ngx-cf-ip projects which were deprecated and combined under a single name. 
