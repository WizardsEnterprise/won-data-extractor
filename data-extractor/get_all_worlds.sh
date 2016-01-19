for world in {1..22}
do
	echo "World $world"
	php get_leaderboards.php $world > /var/log/won-data-extractor/get_leaderboards_"$world"_`date +\%Y\%m\%d\%H\%M`.log
	php get_world.php $world > /var/log/won-data-extractor/get_world_"$world"_`date +\%Y\%m\%d\%H\%M`.log
	php clean_ws_logs.php > /var/log/won-data-extractor/clean_ws_logs_`date +\%Y\%m\%d`.log
done