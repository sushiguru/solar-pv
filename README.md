solar-pv
========

Code for interrogating and storing solar pv generated data from a Solarmax 
inverter to a mySQL database

This is tested and is operational at my house, which has a Solarmax 2000S 
inverter in a 4kW system.

The original script for communicating with the inverter can be found at:
http://blog.dest-unreach.be/2009/04/15/solarmax-maxtalk-protocol-reverse-engineered

and I have translated it into PHP and then edited it a bit.

The calculations for solar position are from:
http://www.srrb.noaa.gov/highlights/sunrise/calcdetails.html where I used the 
http://www.srrb.noaa.gov/highlights/sunrise/NOAA_Solar_Calculations_day.xls 
spreadsheet and translated the equations to PHP.

To install/configure:

Locally
------------------------------------------------------------------------------
Put the solarmax folder onto a *nix server in your house (!); I have an Ubuntu
Server as a local test server and file server, which runs the cron job.

Setup the local configuration variables in config.php (see below)

Setup the cron job:
*/5 * * * * php /path/to/pv.php >/dev/null

Remotely
------------------------------------------------------------------------------
The work was done based on a CodeIgniter installation site, so you will have to rework this if you put it elsewhere.
Copy 	controller.php	-> your CI controllers folder
		pv_model.php	-> your CI models folder
		view.php		-> your CI views folder
		
To do:
------------------------------------------------------------------------------
Re-work the entire dashboard for multiple pretty graphs and output data, with an
ajax auto-update feature

Tidy up the functions, wrap the whole lot into a class, possibly

Enable recording of OPSTATES
 