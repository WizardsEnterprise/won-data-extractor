# Get all world leaderboards *including* world 13
for world in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20 21 22
do
	echo "World $world"

	php $WON_DIR/data-extractor/get_leaderboards.php $world > $WON_LOGS/get_leaderboards_"$world"_`date +\%Y\%m\%d\%H\%M`.log
done

php $WON_DIR/data-extractor/clean_ws_logs.php >> $WON_LOGS/clean_ws_logs_`date +\%Y\%m\%d`.log