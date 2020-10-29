#!/usr/bin/env php
<?php
/*
* ip-list-updater
* Automatic CDN and bogon IP list updater for firewall and server configurations
* Copyright (c) 2017 Volkan Kucukcakar
*
* ip-list-updater is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 2 of the License, or
* (at your option) any later version.
*
* ip-list-updater is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* This copyright notice and license must be retained in all files and derivative works.
*/

class ip_list_updater {

	// Short name
	private static $app_name = "ip-list-updater";
	// Version
	private static $app_version = "1.1.0";
	// Description
	private static $app_description = "Automatic CDN and bogon IP list updater for firewall and server configurations";
	/*
	// PID file
	private static $pid_file = "/var/run/ip-list-updater.pid";
	*/
	// URLs of some pre-defined sources of bogon IP lists and CDN IP ranges
	public static $pd_sources = array(

		// Bogon IP lists:

		// Spamhaus URLs for "spamhaus" keyword
		'spamhaus'	=> 'https://www.spamhaus.org/drop/drop.txt https://www.spamhaus.org/drop/edrop.txt https://www.spamhaus.org/drop/dropv6.txt',
		// Team Cymru URLs for "cymru" keyword
		'cymru'		=> 'http://www.team-cymru.org/Services/Bogons/fullbogons-ipv4.txt http://www.team-cymru.org/Services/Bogons/fullbogons-ipv6.txt',

		// CDN IP ranges:

		// Cloudflare URLs for "cloudflare" keyword
		'cloudflare'	=> 'https://www.cloudflare.com/ips-v4 https://www.cloudflare.com/ips-v6',
		// Maxcdn URLs for "maxcdn" keyword
		'maxcdn'		=> 'https://www.maxcdn.com/one/assets/ips.txt',
		// Amazon CloudFront URLs for "cloudfront" keyword
		'cloudfront'	=> 'http://d7uri8nf7uskq.cloudfront.net/tools/list-cloudfront-ips',
		// Fastly URLs for "fastly" keyword
		'fastly'		=> 'https://api.fastly.com/public-ip-list',
	);
	// Maximum number of IP addresses to use with array operations (10.000)
	public static $max_ip_array = 10000;
	// Script time limit just in case (900 seconds)
	public static $time_limit = 900;
	// Script memory limit just in case (64MB)
	public static $memory_limit = "64M";

	// The following properties also reflect command-line parameters:

	// Update
	public static $update = false;
	// Force update
	public static $force = false;
	// Operation mode (raw|ipset|nginx|apache)
	public static $mode = "";
	// Success command
	public static $success = "";
	// Timeout (Seconds)
	public static $timeout = 30;
	// No certificate check (true|false)
	public static $nocert = false;
	// Validate IP version (4|6|all*)
	public static $ipv = "all";
	// Output filename for raw text output
	public static $output = "";
	// Ipset setname
	public static $setname = "";
	// Ipset path
	public static $ipset_path = "ipset";
	// IP list download URLs separated by space
	public static $sources = "";
	// Display help
	public static $help = false;
	// Display version and license information
	public static $version = false;


	/*
	* Shutdown callback
	*/
	/*
	static function shutdown() {
		unlink( self::$pid_file );
	}// function
	*/

	/*
	* Custom error exit function that writes error string to STDERR and exits with 1
	*/
	static function error( $error_str ) {
		fwrite( STDERR, $error_str );
		exit( 1 );
	}// function

	/*
	* Display version and license information
	*/
	static function version() {
		echo self::$app_name . " v" . self::$app_version . "\n"
			. self::$app_description . "\n"
			. "Copyright (c) 2017 Volkan Kucukcakar \n"
			. "License GPLv2+: GNU GPL version 2 or later\n"
			. " <https://www.gnu.org/licenses/gpl-2.0.html>\n"
			. "Use option \"-h\" for help\n";
		exit;
	}// function

	/*
	* Display help
	*/
	static function help( $long = false ) {
		echo self::$app_name . "\n" . self::$app_description . "\n";
		if ( $long ) {
			echo "Usage: " . self::$app_name . ".php [OPTIONS]\n\n"
				. "Available options:\n"
				. " -u, --update *\n"
				. "     Download IP lists and update the configuration files\n"
				. " -f, --force\n"
				. "     Force update\n"
				. " -m, --mode *\n"
				. "     Operation mode (raw*|ipset|nginx|apache)\n"
				. " -c <command>, --success=<command>\n"
				. "     Success command to execute after a successful update\n"
				. " -t <seconds>, --timeout=<seconds>\n"
				. "     Set download timeout\n"
				. " -n, --nocert\n"
				. "     No certificate check\n"
				. " -x, --ipv\n"
				. "     Validate IP version (4|6|all*)\n"
				. " -o <filename>, --output=<filename> *\n"
				. "     Write IP list to output file (old file will be overwritten)\n"
				. " -e <setname>, --setname=<setname> *\n"
				. "     Add IP list to ipset with setname (old list will be removed)\n"
				. " --ipset_path=<path> *\n"
				. "     Change default Ipset path\n"
				. " -s <urls>, --sources=<urls>\n"
				. "     Set download sources (\"cloudflare\", \"cymru\" \n"
				. "     \"cloudflare\", \"maxcdn\", \"cloudfront\", \"fastly\" \n"
				. "     keywords or space separated custom URLs)\n"
				. " -v, --version\n"
				. "     Display version and license information\n"
				. " -h, --help\n"
				. "     Display help\n"
				. "\nExamples (raw mode):\n"
				. "\$ " . self::$app_name . ".php -u -m raw -x 4 -o \"/etc/ip-list-updater.txt\" -s \"cloudflare\" -c \"/etc/myscript.sh\" \n"
				. "\$ " . self::$app_name . ".php --update --mode=\"raw\" --ipv=4 --output=\"/etc/ip-list-updater.txt\" --sources=\"https://www.cloudflare.com/ips-v4\" --success=\"/etc/myscript.sh\"\n"
				. "\nExamples (ipset mode):\n"
				. "\$ " . self::$app_name . ".php --update --mode=\"ipset\" --setname=\"allowlist\" --ipv=4 --output=\"/etc/allowlist.txt\" --sources=\"cloudflare\"\n"
				. "\$ " . self::$app_name . ".php --update --mode=\"ipset\" --setname=\"bogonlist\" --ipv=4 --output=\"/etc/bogonlist.txt\" --sources=\"spamhaus\"\n"
				. "\nExamples (nginx mode):\n"
				. "\$ " . self::$app_name . ".php --update --mode=\"nginx\" --ipv=4 --output=\"/etc/nginx-cloudflare.conf\" --sources=\"cloudflare\" --success=\"/usr/bin/nginx -s reload\"\n"
				. "\nPlease see README.md for more detailed examples and usage cases.\n"
				. "\n";
			exit;
		} else {
			echo "Use option \"-h\" for help\n";
			exit ( 1 );
		}
	}// function

	/*
	* Update IP addresses
	*/
	static function update() {
		// Check if output file is defined
		if ( '' == self::$output ) {
			self::error( "Error: Output file undefined. Please use -o, --output option to define output file. Use option \"-h\" for help.\n" );
		}
		// Check if sources are defined
		if ( '' == self::$sources ) {
			self::error( "Error: No sources are defined. Please use -s, --sources option with one or more URLs or keywords to define sources. Use option \"-h\" for help\n" );
		}
		// Check if operation mode is defined
		if ( '' == self::$mode ) {
			echo "Operation mode is not defined, assuming \"raw\" mode.\n";
		}
		// Check if ipset is defined in ipset mode
		if ( 'ipset' == self::$mode && '' == self::$setname ) {
			self::error( "Error: Setname must be defined in ipset mode. Please use -e, --setname option with setname. Use option \"-h\" for help\n" );
		}
		// Check if ipv is defined in ipset mode
		if ( 'ipset' == self::$mode && ( '4' != self::$ipv && '6' != self::$ipv ) ) {
			self::error( "Error: IP version must be defined in ipset mode. Please use -x, --ipv option with 4 or 6. Use option \"-h\" for help\n" );
		}
		// Read old list from file
		$old_list = @file_get_contents( self::$output );
		// Check current output file data to avoid overwriting arbitrary files or configuration data
		if ( false !== $old_list && !preg_match( '~(?:^\s*### ip-list-updater ###|^[\s\d\.:/a-f]*$)~is', $old_list ) ){
			self::error( "Error: Current output file contains unknown data. Please clear or delete output file after checking to avoid overwriting arbitrary files or configuration data.\n" );
		}
		// Replace keywords with real urls of pre-defined sources
		foreach ( self::$pd_sources as $key => $value ) {
			self::$sources = preg_replace( '~(?<![^\s])' . $key . '(?![^\s])~is', $value, self::$sources );
		}
		// Download ip list
		$options = array(
			'http' => array(
				'timeout' => self::$timeout
			),
			'ssl' => array(
				'verify_peer' => ! self::$nocert
			)
		);
		$context  = stream_context_create( $options );
		$ip_list = '';
		$ip_count = 0;
		foreach ( preg_split( '~\s+~', self::$sources ) as $url ) {
			if ( !preg_match( '~^https?://~i', $url ) ) {
				self::error( "Error: URL or keyword is not valid.( " . $url . " )\n" );
			}
			echo "Downloading: " . $url . "\n";
			$download_data = file_get_contents( $url, false, $context );
			if ( false !== $download_data ) {
				// Strip comments on lines except xml and json files
				if (!preg_match('~^\s*(<.+>|{".+})\s*$~is', $download_data)) {
					$download_data = preg_replace('~[;#][^\r\n]+~is', '', $download_data);
				}
				// In the past, I have been using arrays here, but PHP arrays are memory hogs because of their overhead in structures.
				// Large lists cause problems even with SplFixedArray, which is better but still terrible. PHP is not C.
				// In conclusion, I have limited the usage of arrays.

				// Parse IP list, store in string as raw IP list
				$ip_list_valid = false;
				$token=" \r\n,\"'()[]<>";
				$pair = strtok( $download_data, $token );
				while ( $pair !== false ) {
					// Validate IP after extracting mask
					list( $ip ) = explode( "/", $pair );
					if ( '4' == self::$ipv ) {
						$filter_flags = FILTER_FLAG_IPV4;
					} elseif ( '6' == self::$ipv ) {
						$filter_flags = FILTER_FLAG_IPV6;
					} else {
						$filter_flags = 0;
					}
					// Add only validated IP addresses. IPv4, IPv6 or both
					if ( !filter_var( $ip, FILTER_VALIDATE_IP, $filter_flags ) === false ) {
						$ip_list .= $pair . "\n";
						$ip_count++;
					}
					// Mark list as valid if it contains any IPv4 or IPv6 address
					if ( !$ip_list_valid && filter_var( $ip, FILTER_VALIDATE_IP ) !== false ) {
						$ip_list_valid = true;
					}
					$pair = strtok( $token );
				}
				// Free up some memory
				unset( $download_data );
				strtok( '', '' );
				// Downloaded IP list is invalid
				if ( ! $ip_list_valid ) {
					self::error( "Error: IP list downloaded is not valid.( " . $url . " )\n" );
				}
			} else {
				self::error( "Error: Download failed for URL.( " . $url . " )\n" );
			} //if
		}// foreach
		// Remove duplicate IP addresses
		if ( $ip_count <= self::$max_ip_array ) {
			$a_ip_list = explode( "\n", trim( $ip_list ) ) ;
			$non_unique_count = count( $a_ip_list );
			$a_ip_list = array_unique( $a_ip_list );
			$unique_count = count( $a_ip_list );
			if ( $unique_count < $non_unique_count ) {
				echo "Removed " . ( $non_unique_count - $unique_count ) . " duplicate entries.\n";
				$ip_list = implode( "\n", $a_ip_list ) . "\n";
			}
		}
		// Construct output data according to operation modes (ipset mode uses raw output too)
		$output_prefix = "### ip-list-updater ###\n# Please do not manually edit this file, any modifications will be overwritten!\n";
		if ( 'nginx' == self::$mode ) {
			$output_data = $output_prefix;
			$pair = strtok( $ip_list, "\n" );
			while ( $pair !== false ) {
				$output_data .= "set_real_ip_from " . $pair . ";\n";
				$pair = strtok( "\n" );
			}
			strtok( '', '' );
		} elseif ( 'apache' == self::$mode ) {
			$output_data = $output_prefix . $ip_list;
		} else {
			$output_data = $ip_list;
		}
		// Check if update required
		if ( ( self::$force ) || ( false === $old_list ) || ( $old_list !== $output_data ) ) {
			echo $ip_count . " total IP address/netmask pairs found.\n";
			// Switch mode
			switch ( self::$mode ) {
				// ipset mode
				case "ipset":
					echo "Running in \"ipset\" mode\n";
					// Update Ipset
					echo "Updating Ipset: " . self::$setname . "\n";
					if ( '' != self::$setname ) {
						// Check if Ipset is installed
						exec( self::$ipset_path . ' --version >/dev/null 2>&1', $output, $return_var );
						if ( $return_var !== 0 ) {
							self::error( "Error: ipset command failed. ipset may not be installed or not found.\nInstall ipset or try --ipset_path parameter.\n" );
						}
						// Check if set exists, get set headers
						exec( self::$ipset_path . ' -t list ' . self::$setname, $output, $return_var );
						if ( $return_var !== 0 ) {
							self::error( "Error: Ipset " . self::$setname . " not exists. You can try creating a new set.\ni.e.:$ ipset create " . self::$setname . " hash:net family inet hashsize 1024 maxelem 131072\n" );
						}
						// Parse set headers, get ip version (inet|inet6), maximum number of elements
						$family = ( preg_match( '~family\s([^\s]+)~is', implode( "\n", $output ), $m ) ) ? $m[1] : '';
						$maxelem = ( preg_match( '~maxelem\s([^\s]+)~is', implode( "\n", $output ), $m ) ) ? $m[1] : '0';
						// Check IP version compatibility of set
						if ( '4' == self::$ipv && "inet6" == $family ) {
							self::error( "Error: Ipset " . self::$setname . " is not IPv4 compatible. You can try creating an IPv4 compatible set.\ni.e.:$ ipset create " . self::$setname . " hash:net family inet hashsize 1024 maxelem 131072\n" );
						}
						if ( '6' == self::$ipv && "inet" == $family ) {
							self::error( "Error: Ipset " . self::$setname . " is not IPv6 compatible. You can try creating an IPv6 compatible set.\ni.e.:$ ipset create " . self::$setname . " hash:net family inet6 hashsize 1024 maxelem 131072\n" );
						}
						// Check if set support the number of elements
						if ( $ip_count > $maxelem ) {
							self::error( "Error: Ipset " . self::$setname . " does not support that number of elements. You can try creating a larger set.\ni.e.:$ ipset create " . self::$setname . " hash:net family " . $family . " hashsize 1024 maxelem " . ( ($ip_count < 131072) ? 131072 : ( $ip_count + 1000 ) ) . "\n" );
						}
						// Create array from old ip list
						if ( false !== $old_list ) {
							$a_old_list = explode( "\n", trim( $old_list ), self::$max_ip_array ) ;
							// If the last element is not a single IP, then the list must be greater than maximum allowed ip addresses to use with arrays
							if ( strstr( $a_old_list[count( $a_old_list ) - 1], "\n" ) ) {
								$a_old_list = false;
							}
						} else {
							$a_old_list = false;
						}
						// Create proc_open() descriptorspec
						$descriptorspec = array(
							0 => array("pipe", "r"), // stdin
							1 => array("pipe", "w"), // stdout
							2 => array("pipe", "w"), // stderr
						);
						// Validate old IP list (required when updating ipset by arrays)
						if ( $a_old_list!==false ) {
							$old_list_valid = true;
							foreach( $a_old_list as $pair ) {
								// Validate IP after extracting mask
								list( $ip ) = explode( "/", $pair );
								if ( '4' == self::$ipv ) {
									$filter_flags = FILTER_FLAG_IPV4;
								} elseif ( '6' == self::$ipv ) {
									$filter_flags = FILTER_FLAG_IPV6;
								} else {
									$filter_flags = 0;
								}
								if ( filter_var( $ip, FILTER_VALIDATE_IP, $filter_flags ) === false ) {
									$old_list_valid = false;
									break;
								}
							}// foreach
						}// if
						// Update ipset using arrays if ip list and old ip list are not too large
						if ( $ip_count <= self::$max_ip_array && false !== $a_old_list && true == $old_list_valid ) {
							echo "Updating in zero downtime mode.\n";
							// Add new IP addresses to set (ipset restore command is by far faster than one by one execution)
							$process = proc_open( self::$ipset_path . ' restore -!', $descriptorspec, $pipes );
							if ( is_resource( $process ) ) {
								// Remove IP address/netmask pairs that exist in old list but does not exist in new list (Keep valid items in set for zero downtime)
								$a_diff = array_diff( $a_old_list, $a_ip_list );
								foreach ( $a_diff as $pair ) {
									// Write new lines of restore (del) commands into input pipe
									fwrite( $pipes[0], "del " .  self::$setname . " " . $pair . "\n" );
								}
								// Add new IP address/netmask pairs to set (Add all items without diff to make sure set is not manually modified after old list is written)
								foreach ( $a_ip_list as $pair ) {
									// Write new lines of restore (add) commands into input pipe
									fwrite( $pipes[0], "add " .  self::$setname . " " . $pair . "\n" );
								}
								fclose($pipes[0]);
								$output = stream_get_contents($pipes[1]);
								$error_output = stream_get_contents($pipes[2]);
								fclose($pipes[1]);
								fclose($pipes[2]);
								// Close all pipes before proc_close() in order to avoid a deadlock
								$return_var = proc_close( $process );
								if ( $return_var !== 0 ) {
									echo $output;
									echo $error_output;
									self::error( "Error: ipset restore command failed.\n" );
								}
							} else {
								self::error( "Error: proc_open() failed while executing ipset.\n" );
							}// if
						} else {
							// Flush set
							exec( self::$ipset_path . ' flush ' . self::$setname . ' >/dev/null', $output, $return_var );
							if ( $return_var !== 0 ) {
								self::error( "Error: ipset flush command failed.\n" );
							}
							// Add new IP addresses to set (restore command is by far faster than one by one execution)
							$process = proc_open( self::$ipset_path . ' restore -!', $descriptorspec, $pipes );
							if ( is_resource( $process ) ) {
								// Write new lines of restore commands into input pipe
								$pair = strtok( $ip_list, "\n" );
								while ( $pair !== false ) {
									fwrite( $pipes[0], "add " .  self::$setname . " " . $pair . "\n" );
									$pair = strtok( "\n" );
								}
								strtok( '', '' );
								fclose($pipes[0]);
								$output = stream_get_contents($pipes[1]);
								$error_output = stream_get_contents($pipes[2]);
								fclose($pipes[1]);
								fclose($pipes[2]);
								// Close all pipes before proc_close() in order to avoid a deadlock
								$return_var = proc_close( $process );
								if ( $return_var !== 0 ) {
									echo $output;
									echo $error_output;
									self::error( "Error: ipset restore command failed.\n" );
								}
							} else {
								self::error( "Error: proc_open() failed while executing ipset.\n" );
							}// if
						}// if
						echo "Ipset updated.\n";
					}// if
					break;
				// nginx mode
				case "nginx":
					echo "Running in \"nginx\" mode\n";
					break;
				// apache mode
				case "apache":
					echo "Running in \"apache\" mode\n";
				// raw mode
				case "raw":
				case "":
					echo "Running in \"raw\" mode\n";
					break;
				default:
					echo "Mode unknown, assuming \"raw\" mode\n";
			}// switch
		} else {
			// IP list not updated
			echo "No changes detected, IP list is up to date.\n";
			// Return and gracefully end the script
			return;
		}
		// Write output file
		if ( false !== file_put_contents( self::$output, $output_data ) ) {
			echo "Output file written.\n";
		} else {
			self::error( "Error: Output file \"" . self::$output . "\" could not be written.\n" );
		}
		// Execute success command
		if ( '' != self::$success ) {
			passthru( self::$success, $return_var );
			if ( $return_var === 0 ) {
				echo "Success command executed without errors.\n";
			} else {
				self::error( "Error: Success command failed.\n" );
			}
		}
	}// function

	/*
	* Initial function to run
	*/
	static function run() {
		// Set error reporting to report all errors except E_NOTICE
		error_reporting( E_ALL ^ E_NOTICE );
		// Set script time limit just in case
		set_time_limit( self::$time_limit );
		// Set script memory limit just in case
		ini_set( 'memory_limit', self::$memory_limit );
		// PHP version check
		if ( version_compare( PHP_VERSION, '5.3.0', '<' ) ) {
			self::error( "Error: This application requires PHP 5.3.0 or later to run. PHP " . PHP_VERSION . " found. Please update PHP-CLI.\n" );
		}
		/*
		* Single instance check that I have implemented in predecessors of ip-list-updater is no longer required
		* as memory consumption is slightly decreased after some optimizations in array usage
		* and it also has disadvantages because of the multiple mode nature of the new script.
		*/
		/*
		// Single instance check
		$pid = @file_get_contents( self::$pid_file );
		if ( false !== $pid ) {
			// Check if process is running using POSIX functions or checking for /proc/PID as a last resort
			$pid_running = ( function_exists( 'posix_getpgid' ) ) ? ( false !== posix_getpgid( $pid ) ) : file_exists( '/proc/'.$pid );
			if ( $pid_running ) {
				self::error( "Error: Another instance of script is already running. PID:" . $pid . "\n" );
			} else {
				// Process is not really running, delete pid file
				unlink( self::$pid_file );
			}
		}
		file_put_contents( self::$pid_file, getmypid() );
		register_shutdown_function( array( __CLASS__, 'shutdown' ) );
		*/
		// Load "openssl" required for file_get_contents() from "https"
		if ( ( !extension_loaded( 'openssl' ) ) && ( function_exists( 'dl' ) ) ) {
			dl( 'openssl.so' );
		}
		// Check if allow_url_fopen enabled
		if ( 0 == ini_get( "allow_url_fopen" ) ) self::error( "Error: 'allow_url_fopen' is not enabled in php.ini for php-cli.\n" );
		// Check if openssl loaded
		if ( !extension_loaded( 'openssl' ) ) self::error( "Error: 'openssl' extension is not loaded php.ini for php-cli and cannot be loaded by dl().\n" );
		// Parse command line arguments and gets options
		$options = getopt( "ufm:c:t:nx:o:e:s:vh", array( "update", "force", "mode:", "success:", "timeout:", "nocert", "ipv:", "output:", "setname:", "ipset_path:", "sources:", "version", "help" ) );
		$stl = array( "u" => "update", "f" => "force", "m" => "mode", "c" => "success", "t" => "timeout", "n" => "nocert", "x" => "ipv", "o" => "output", "e" => "setname", "ipset_path" => "ipset_path", "s" => "sources", "v" => "version", "h" => "help");
		foreach ( $options as $key => $value ) {
			if ( 1 == strlen( $key ) ) {
				// Translate short command line options to long ones
				self::${$stl[$key]} = ( is_string( $value ) ) ? $value : true;
			} else {
				// Set class variable using option value or true if option do not accept a value
				self::${$key} = ( is_string( $value ) ) ? $value : true;
			}
		}
		// Keep timeout value in a meaningful range
		self::$timeout = ( self::$timeout <= 300 ) ? ( ( self::$timeout > 5 ) ? self::$timeout : 5 ) : 300;
		// Display version and license information
		if ( self::$version )
			self::version();
		// Display long help & usage (on demand)
		if ( self::$help )
			self::help( true );
		// Display short help (on error)
		if ( ! self::$update )
			self::help( false );
		// Update IP addresses
		self::update();
	}// function

}// class

ip_list_updater::run();
