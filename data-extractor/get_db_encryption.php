<?php
/*
$ac = array('9', 'h', 'T', 'D', 'v', 'O', '5', 'Z', 'f', 'w', 
            'd', 'x', 'X', '1', 'J', 'h', 'u', 'Q', 'g', 'R');
$ac1 = array('A', 't', 'j', 'k', 'd', '6', 'I', 'n', 'W', '7', 
            'L', 'q', 'B', 'X', 'W', 'I', 'g', '6', 'T', '7');
*/

$ac = array('O', 'E', 'M', 'U');
$ac1 = array('T', '4', 'z', '3');


$key = '';
for($i = 0; $i < count($ac); $i++) {
	$key .= $ac[$i].$ac1[$i];
}

echo "$key\r\n";

// 9AhtTjDkvdO65IZnfWw7dLxqXB1XJWhIugQ6gTR7
// http://static-cdn.gcios.gree-apps.net/hc/images/asset_index/AssetIndex.db
// http://static-cdn.gcios.gree-apps.net/hc/sqlite/hc_NA_20151123_60317/GameDataProvider.db

// OTE4MzU3

/*
Stevens-MBP:sqlcipher-master Steven$ ./sqlcipher ../GameDataProvider_20160818_77083.db 
SQLCipher version 3.8.10.2 2015-05-20 18:17:19
Enter ".help" for instructions
Enter SQL statements terminated with a ";"
sqlite> PRAGMA key='9AhtTjDkvdO65IZnfWw7dLxqXB1XJWhIugQ6gTR7';
sqlite> PRAGMA cipher_migrate;
0
sqlite> .databases
seq  name             file                                                      
---  ---------------  ----------------------------------------------------------
0    main             /Users/Steven/Documents/War of Nations/Databases/sqlcipher
sqlite> ATTACH DATABASE 'plaintext_20160818_77083.db' as plaintext KEY '';
sqlite> SELECT sqlcipher_export('plaintext');

sqlite> DETACH DATABASE plaintext;
sqlite> .exit
Stevens-MBP:sqlcipher-master Steven$ pwd
/Users/Steven/Documents/War of Nations/Databases/sqlcipher-master
Stevens-MBP:sqlcipher-master Steven$ 
*/
?>