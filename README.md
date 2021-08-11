# Introduction
PHP导出MySQL数据库数据字典 可以导出为Word文档和Excel表格以及Markdown、HTML格式

# Install
```
composer require selfim/data_dictionary 
```
# Demo
```
require __DIR__ . '/vendor/autoload.php';
$result = DataDict::init(['dbname'=>'your database','host'=>'your host','user'=>'username','password'=>'password'])
    ->make('html');
print_r($result);
```