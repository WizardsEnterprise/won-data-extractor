for world in 1 2 3 4 5 6 7 8 9 10 11 12 14 15 16 17 18 19 20 21 22
do
	echo "World $world"

	php /opt/won-data-extractor/data-extractor/get_leaderboards.php $world > /var/log/won-data-extractor/get_leaderboards_"$world"_`date +\%Y\%m\%d\%H\%M`.log
	php /opt/won-data-extractor/data-extractor/get_world.php $world > /var/log/won-data-extractor/get_world_"$world"_`date +\%Y\%m\%d\%H\%M`.log
	php /opt/won-data-extractor/data-extractor/clean_ws_logs.php > /var/log/won-data-extractor/clean_ws_logs_`date +\%Y\%m\%d`.log
done