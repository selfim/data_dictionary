# Introduction
mysql data dictionary export

#Install
```
composer require selfim/data_dictionary @dev
```
#Demo
```
require __DIR__ . '/vendor/autoload.php';
$dataObj = new DataDict(['dsn'=>'mysql:dbname=mytest'.';host=localhost','user'=>'root','password'=>'qaz123456']);
$res = $dataObj->make();
```