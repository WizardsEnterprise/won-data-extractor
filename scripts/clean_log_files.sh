tar -cjvf $WON_LOGS/archive/logs_`date +\%Y\%m\%d`.tar.bz $WON_LOGS/*`date +\%Y\%m\%d`*.log  
rm $WON_LOGS*`date +\%Y\%m\%d`*.log