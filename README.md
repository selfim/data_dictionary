# Introduction
PHP导出MySQL数据库数据字典 可以导出为Word文档和Excel表格以及Markdown、HTML格式

# Install
```
composer require selfim/data_dictionary 
```
# Demo
```
require __DIR__ . '/vendor/autoload.php';
use DictionaryExport\DataDict;

//默认为Markdown格式 
DataDict::init(['dbname'=>'your database','host'=>'your host','user'=>'username','password'=>'password'])
    ->make();
    
//导出word
DataDict::init(['dbname'=>'your database','host'=>'your host','user'=>'username','password'=>'password'])
    ->make('doc');
    
//导出excel
DataDict::init(['dbname'=>'your database','host'=>'your host','user'=>'username','password'=>'password'])
    ->make('xls');    

//输出为html
DataDict::init(['dbname'=>'your database','host'=>'your host','user'=>'username','password'=>'password'])
    ->make('html');      
```