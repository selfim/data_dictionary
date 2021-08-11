<?php
declare (strict_types=1);


namespace DictionaryExport;

use PDO;
class DataDict
{
    protected $db;
    protected  $database;
    public function __construct(array $config)
    {
        $this->database = $config['dbname'];
        $this->db = new PDO('mysql:dbname='.$config['dbname'].';host='.$config['host'], $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db->query('SET NAMES utf8');
    }
    public  function make(string $type = 'markdown')
    {
        switch ($type) {
            case 'html':
                return $this->generateHtml();
            case 'json':
                return $this->generateJson();
            default:
                return $this->generateMd();
        }
    }
    protected  function getTables()
    {
        $list = $tmp = [];
        $res = $this->db->query("SHOW TABLE STATUS");
        //echo'<pre>';var_export($res);die();
        while ( $row =  $res->fetch(PDO::FETCH_ASSOC)) {
            //$tables [] ['TABLE_NAME'] = current($row);
            $tmp['name']    = $row['Name'];
            $tmp['comment'] = $row['Comment'];
            $tmp['engine']  = $row['Engine'];
            $list[]         = $tmp;
        }
        unset($tmp);
        return $list;
    }
    protected function getColumns(string $table)
    {
        if (empty($table)) {
            return false;
        }
        $list = $tmp = [];
        $columns = $this->db->query('SHOW FULL COLUMNS FROM ' . $table);
        while ( $row =  $columns->fetch(PDO::FETCH_ASSOC)) {
            $tmp['field']     = $row['Field'];
            $tmp['type']      = $row['Type'];
            $tmp['collation'] = $row['Collation'];
            $tmp['default']   = $row['Default'];
            $tmp['null']      = $row['Null'];
            $tmp['extra']     = $row['Extra'];
            $tmp['comment']   = $row['Comment'];
            $list[]           = $tmp;
        }
        unset($tmp);
        return $list;
    }
    protected  function generateHtml()
    {
        $title = $this->database.'数据库'. '数据字典';
        $tables   = $this->getTables();

        foreach ( $tables as $k => $v ) {
            $sql = 'SELECT * FROM ';
            $sql .= 'INFORMATION_SCHEMA.TABLES ';
            $sql .= 'WHERE ';
            $sql .= "table_name = '{$v['name']}'  AND table_schema = '{$this->database}'";
            $res = $this->db->query ( $sql );
            while ( $t = $res->fetch(PDO::FETCH_ASSOC) ) {
                $tables [$k] ['TABLE_COMMENT'] = $t ['TABLE_COMMENT'];
            }

            $sql = 'SELECT * FROM ';
            $sql .= 'INFORMATION_SCHEMA.COLUMNS ';
            $sql .= 'WHERE ';
            $sql .= "table_name = '{$v['name']}' AND table_schema = '{$this->database}'";

            $fields = [];
            $res = $this->db->query ( $sql );
            while ( $t = $res->fetch(PDO::FETCH_ASSOC) ) {
                $fields [] = $t;
            }
            $tables [$k] ['COLUMN'] = $fields;
        }
        $html = '';
// 循环所有表
        foreach ( $tables as $k => $v ) {
            // $html .= '<p><h2>'. $v['TABLE_COMMENT'] . ' </h2>';
            $html .= '<table  border="1" cellspacing="0" cellpadding="0" align="center">';
            $html .= '<caption>' . $v ['name'] . '  ' . $v ['TABLE_COMMENT'] . '</caption>';
            $html .= '<tbody><tr><th>字段名</th><th>数据类型</th><th>默认值</th>
    <th>允许非空</th>
    <th>自动递增</th><th>备注</th></tr>';
            $html .= '';

            foreach ( $v ['COLUMN'] as $f ) {
                $html .= '<tr><td class="c1">' . $f ['COLUMN_NAME'] . '</td>';
                $html .= '<td class="c2">' . $f ['COLUMN_TYPE'] . '</td>';
                $html .= '<td class="c3"> ' . $f ['COLUMN_DEFAULT'] . '</td>';
                $html .= '<td class="c4"> ' . $f ['IS_NULLABLE'] . '</td>';
                $html .= '<td class="c5">' . ($f ['EXTRA'] == 'auto_increment' ? '是' : ' ') . '</td>';
                $html .= '<td class="c6"> ' . $f ['COLUMN_COMMENT'] . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table></p>';
        }
        $header = '<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>' . $title . '</title>
<style>
body,td,th {font-family:"宋体"; font-size:12px;}
table{border-collapse:collapse;border:1px solid #CCC;background:#6089D4;}
table caption{text-align:left; background-color:#fff; line-height:2em; font-size:14px; font-weight:bold; }
table th{text-align:left; font-weight:bold;height:26px; line-height:25px; font-size:16px; border:3px solid #fff; color:#ffffff; padding:5px;}
table td{height:25px; font-size:12px; border:3px solid #fff; background-color:#f0f0f0; padding:5px;}
.c1{ width: 150px;}
.c2{ width: 130px;}
.c3{ width: 70px;}
.c4{ width: 80px;}
.c5{ width: 80px;}
.c6{ width: 300px;}
</style>
</head>
<body>';
        return  $header.'<h1 style="text-align:center;">' . $title . '</h1>'.$html.'</body></html>';

    }
    protected  function generateJson()
    {
        $tables = $this->getTables();
        //循环所有表
        foreach ($tables as &$val) {
            $val['item'] = $this->getColumns($val['name']);
        }
        unset($val);
        return json_encode($tables);
    }
    protected  function generateMd()
    {
        $title  = '数据字典';
        $mark   = '';
        $tables = $this->getTables();
        //循环所有表
        foreach ($tables as $val) {
            $info = $this->getColumns($val['name']);
            $mark .= '## ' . $val['name'] . ' 【' . $val['engine'] . '】 ' . $val['comment'] . PHP_EOL;
            $mark .= '' . PHP_EOL;
            $mark .= '|  字段名  |  数据类型  |  默认值  |  允许非空  |  自动递增  |  备注  |' . PHP_EOL;
            $mark .= '| ------ | ------ | ------ | ------ | ------ | ------ |' . PHP_EOL;
            foreach ($info as $item) {
                $mark .= '| ' . $item['field'] . ' | ' . $item['type'] . ' | ' . $item['default'] . ' | ' . $item['null'] . ' | ' . ($item['extra'] == 'auto_increment' ? '是' : '') . ' | ' . (empty($item['comment']) ? '-' : str_replace('|', '/', $item['comment'])) . ' |' . PHP_EOL;
            }
            $mark .= '' . PHP_EOL;
        }
        //markdown输出
        $md_tplt = <<<EOT
# {$title}
>   本数据字典由PHP脚本自动导出,字典的备注来自数据库表及其字段的注释(`comment`).开发者在增改库表及其字段时,请在 `migration` 时写明注释,以备后来者查阅.

{$mark}
EOT;
        return $md_tplt;
    }
}