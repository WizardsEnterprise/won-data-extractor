cd /var/log/won-data-extractor/
tar -cjvf logs_`date +\%Y\%m\%d`.tar.bz *`date +\%Y\%m\%d`*.log  
rm *`date +\%Y\%m\%d`*.log