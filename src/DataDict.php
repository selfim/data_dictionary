<?php
declare (strict_types=1);


namespace DictionaryExport;

use PDO;
use TCPDF;
class DataDict
{
    protected $db;
    protected $database;
    public $htmlTable = ''; //表格内容
    public $tables = []; //读取的表信息数组
    public $exportTables = []; // 要导出的表

    public function __construct(array $config)
    {
        $this->database = $config['dbname'];
        $this->db = new PDO('mysql:dbname=' . $config['dbname'] . ';host=' . $config['host'], $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db->query('SET NAMES utf8');
    }

    public static function init(array $config): DataDict
    {
        return new self($config);
    }

    public function make(string $type = 'markdown')
    {
        switch ($type) {
            case 'html':
                return $this->generateHtml();
            case 'json':
                return $this->generateJson();
            case 'doc':
                return $this->generateDoc();
            case 'xls':
                return $this->generateXls();
            case 'pdf':
                return $this->generatePdf();
            default:
                return $this->generateMd();
        }
    }

    public function common()
    {
        $tables = $this->getTables();
        foreach ($tables as $k => $v) {

            $sql = 'SELECT * FROM ';
            $sql .= 'INFORMATION_SCHEMA.TABLES ';
            $sql .= 'WHERE ';
            $sql .= "table_name = '{$v['name']}'  AND table_schema = '{$this->database}'";
            $res = $this->db->query($sql);
            while ($t = $res->fetch(PDO::FETCH_ASSOC)) {
                $tables [$k] ['TABLE_COMMENT'] = $t ['TABLE_COMMENT'];
            }

            $sql = 'SELECT * FROM ';
            $sql .= 'INFORMATION_SCHEMA.COLUMNS ';
            $sql .= 'WHERE ';
            $sql .= "table_name = '{$v['name']}' AND table_schema = '{$this->database}'";

            $fields = [];
            $res = $this->db->query($sql);
            while ($t = $res->fetch(PDO::FETCH_ASSOC)) {
                $fields [] = $t;
            }
            $tables [$k] ['COLUMN'] = $fields;

            //索引
            $index = [];
            $sql = 'SHOW INDEX FROM ' . "{$v['name']}";
            $res = $this->db->query($sql);
            while ($i = $res->fetch(PDO::FETCH_ASSOC)) {

                $index[] = $i;
            }
            $tables [$k] ['INDEX'] = $this->getIndexInfo($index);
        }
        //组装HTML
        $html = '';
        foreach ($tables as $k => $v) {
            $html .= '<table  border="1" cellspacing="0" cellpadding="0" align="center">';
            $html .= '<h2 align="center">' . $v ['name'] . '  ' . $v ['TABLE_COMMENT'] . '</h2>';
            $html .= '<tbody><tr><th>字段名</th><th>数据类型</th><th>默认值</th>
                        <th>允许非空</th>
            <th>自动递增</th><th>备注(字段数: ' . count($v['COLUMN']) . ')</th></tr>';
            $html .= '';

            foreach ($v ['COLUMN'] as $f) {
                $html .= '<tr><td class="c1">' . $f ['COLUMN_NAME'] . '</td>';
                $html .= '<td class="c2">' . $f ['COLUMN_TYPE'] . '</td>';
                $html .= '<td class="c3"> ' . $f ['COLUMN_DEFAULT'] . '</td>';
                $html .= '<td class="c4"> ' . $f ['IS_NULLABLE'] . '</td>';
                $html .= '<td class="c5">' . ($f ['EXTRA'] == 'auto_increment' ? '是' : ' ') . '</td>';
                $html .= '<td class="c6"> ' . $f ['COLUMN_COMMENT'] . '</td>';
                $html .= '</tr>';
            }
            if (!empty($v['INDEX'])) {
                $html .= '<tr><th colspan="2">索引名</th><th colspan="4">索引顺序</th></tr>';
                foreach ($v['INDEX'] as $indexName => $indexContent) {
                    $html .= '<tr>' . PHP_EOL;
                    $html .= '<td class="c7" colspan="2">' . $indexName . '</td>' . PHP_EOL;
                    $html .= '<td class="c8" colspan="4">' . implode(' > ', $indexContent) . '</td>' . PHP_EOL;
                    $html .= '</tr>' . PHP_EOL;
                }
            }
            $html .= '</tbody></table></p>';
        }
        $this->htmlTable = $html;
        $this->tables = $tables;

        return $this;
    }

    protected function getTables()
    {
        $list = $tmp = [];
        $res = $this->db->query("SHOW TABLE STATUS");
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($this->exportTables)) {
                if (in_array($row['Name'], $this->exportTables)) {
                    $tmp['name'] = $row['Name'];
                    $tmp['comment'] = $row['Comment'];
                    $tmp['engine'] = $row['Engine'];
                    $list[] = $tmp;
                }
            } else {
                $tmp['name'] = $row['Name'];
                $tmp['comment'] = $row['Comment'];
                $tmp['engine'] = $row['Engine'];
                $list[] = $tmp;
            }

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
        while ($row = $columns->fetch(PDO::FETCH_ASSOC)) {
            $tmp['field'] = $row['Field'];
            $tmp['type'] = $row['Type'];
            $tmp['collation'] = $row['Collation'];
            $tmp['default'] = $row['Default'];
            $tmp['null'] = $row['Null'];
            $tmp['extra'] = $row['Extra'];
            $tmp['comment'] = $row['Comment'];
            $list[] = $tmp;
        }
        unset($tmp);
        return $list;
    }

    protected function getIndexInfo($arrIndexInfo)
    {
        $index = [];
        foreach ($arrIndexInfo as $v) {
            $unique = ($v['Non_unique'] == 0) ? '(unique)' : '';
            // $index[$v['Key_name']][] = $v['Seq_in_index'].': '.$v['Column_name'].$unique;
            $index[$v['Key_name']][] = $v['Column_name'] . $unique;
        }

        return $index;
    }

    protected function generateHtml()
    {
        $this->common();
        $title = $this->database . '数据字典';

        $html = '<!DOCTYPE html>
              <html>
              <meta charset="utf-8">
              <title>' . $title . '数据字典</title>
                <style>
                body,td,th {font-family:"宋体"; font-size:12px;}
                table{border-collapse:collapse;border:1px solid #CCC;background:#6089D4;}
                table caption{text-align:left; background-color:#fff; line-height:2em; font-size:14px; font-weight:bold; }
                table th{text-align:left; font-weight:bold;height:26px; line-height:25px; font-size:16px; border:3px solid #fff; color:#ffffff; padding:5px;}
                table td{height:25px; font-size:12px; border:3px solid #fff; background-color:#f0f0f0; padding:5px;}
                tr:hover td{ background-color:#f1f5fb; }
                .c1{ width: 150px;}
                .c2{ width: 130px;}
                .c3{ width: 70px;}
                .c4{ width: 80px;}
                .c5{ width: 80px;}
                .c6{ width: 300px;}
                .c7{ width: 200px;}
                .c8{ width: 515px;}
                </style>
                </head>
                <body>';
        $html .= '<h1 style="text-align:center;">' . $title . '</h1>';
        $html .= '<header><p style="text-align:center;margin:20px auto;">总共：' . count($this->tables) . '个数据表' . "&nbsp" . '生成时间：' . date('Y-m-d H:i:s') . '</p></header>';
        $html .= $this->htmlTable;
        $html .= '</body></html>';
        echo $html;

    }

    protected function generateDoc()
    {
        header("Content-type:text/html;charset=utf-8");
        header("Content-type: application/octet-stream");
        header("Accept-Ranges: bytes");
        header("Content-type:application/vnd.ms-word");
        header('Cache-Control: max-age=0');
        header("Content-Disposition:attachment;filename={$this->database}数据字典.docx");
        $this->common();
        $title = $this->database . '数据字典';


        $html = '<!DOCTYPE html>
              <html>
              <meta charset="utf-8">
              <title>' . $title . '数据字典</title>
                <style>
                body,td,th {font-family:"宋体"; font-size:12px;}
                table{border-collapse:collapse;border:1px solid #CCC;background:#6089D4;}
                table caption{text-align:left; background-color:#fff; line-height:2em; font-size:14px; font-weight:bold; }
                table th{text-align:left; font-weight:bold;height:26px; line-height:25px; font-size:16px; border:3px solid #fff; color:#ffffff; padding:5px;}
                table td{height:25px; font-size:12px; border:3px solid #fff; background-color:#f0f0f0; padding:5px;}
                tr:hover td{ background-color:#f1f5fb; }
                .c1{ width: 150px;}
                .c2{ width: 130px;}
                .c3{ width: 70px;}
                .c4{ width: 80px;}
                .c5{ width: 80px;}
                .c6{ width: 300px;}
                .c7{ width: 200px;}
                .c8{ width: 515px;}
                </style>
                </head>
                <body>';
        $html .= '<h1 style="text-align:center;">' . $title . '</h1>';
        $html .= '<header><p style="text-align:center;margin:20px auto;">总共：' . count($this->tables) . '个数据表' . "&nbsp" . '生成时间：' . date('Y-m-d H:i:s') . '</p></header>';
        $html .= $this->htmlTable;
        $html .= '</body></html>';
        echo $html;
    }

    protected function generateXls()
    {
        header("Content-type: application/vnd.ms-excel; charset=gbk");
        header("Content-Disposition: attachment; filename={$this->database}数据字典.xls");
        $this->common();
        $title = $this->database . '数据字典';


        $html = '<!DOCTYPE html>
              <html>
              <meta charset="utf-8">
              <title>' . $title . '数据字典</title>
                <style>
                body,td,th {font-family:"宋体"; font-size:12px;}
                table{border-collapse:collapse;border:1px solid #CCC;background:#6089D4;}
                table caption{text-align:left; background-color:#fff; line-height:2em; font-size:14px; font-weight:bold; }
                table th{text-align:left; font-weight:bold;height:26px; line-height:25px; font-size:16px; border:3px solid #fff; color:#ffffff; padding:5px;}
                table td{height:25px; font-size:12px; border:3px solid #fff; background-color:#f0f0f0; padding:5px;}
                tr:hover td{ background-color:#f1f5fb; }
                .c1{ width: 150px;}
                .c2{ width: 130px;}
                .c3{ width: 70px;}
                .c4{ width: 80px;}
                .c5{ width: 80px;}
                .c6{ width: 300px;}
                .c7{ width: 200px;}
                .c8{ width: 515px;}
                </style>
                </head>
                <body>';
        $html .= '<h1 style="text-align:center;">' . $title . '</h1>';
        $html .= '<header><p style="text-align:center;margin:20px auto;">总共：' . count($this->tables) . '个数据表' . "&nbsp" . '生成时间：' . date('Y-m-d H:i:s') . '</p></header>';
        $html .= $this->htmlTable;
        $html .= '</body></html>';
        echo $html;
    }

    protected function generateJson()
    {
        $tables = $this->getTables();
        //循环所有表
        foreach ($tables as &$val) {
            $val['item'] = $this->getColumns($val['name']);
        }
        unset($val);
        return json_encode($tables);
    }

    /**
     * 生成markdown格式文档
     * @return string
     */
    protected function generateMd()
    {
        $title = $this->database . '数据字典';
        $tables = $this->getTables();
        foreach ($tables as $k => $v) {

            $sql = 'SELECT * FROM ';
            $sql .= 'INFORMATION_SCHEMA.TABLES ';
            $sql .= 'WHERE ';
            $sql .= "table_name = '{$v['name']}'  AND table_schema = '{$this->database}'";
            $res = $this->db->query($sql);
            while ($t = $res->fetch(PDO::FETCH_ASSOC)) {
                $tables [$k] ['TABLE_COMMENT'] = $t ['TABLE_COMMENT'];
            }

            $fields = [];
            $columns = $this->db->query('SHOW FULL COLUMNS FROM ' . $v['name']);
            while ($t = $columns->fetch(PDO::FETCH_ASSOC)) {
                $fields [] = $t;
            }
            $tables [$k] ['COLUMN'] = $fields;

            //索引
            $index = [];
            $sql = 'SHOW INDEX FROM ' . "{$v['name']}";
            $res = $this->db->query($sql);
            while ($i = $res->fetch(PDO::FETCH_ASSOC)) {

                $index[] = $i;
            }
            $tables [$k] ['INDEX'] = $this->getIndexInfo($index);
        }
        $mark = '';
        foreach ($tables as $val) {

            $mark .= '## ' . $val['name'] . ' 【' . $val['engine'] . '】 ' . $val['comment'] . PHP_EOL;
            $mark .= '' . PHP_EOL;
            $mark .= '|  字段名  |  数据类型  |  默认值  |  允许非空  |  自动递增  |  备注  |' . PHP_EOL;
            $mark .= '| ------ | ------ | ------ | ------ | ------ | ------ |' . PHP_EOL;
            foreach ($val['COLUMN'] as $item) {
                $mark .= '| ' . $item['Field'] . ' | ' . $item['Type'] . ' | ' . $item['Default'] . ' | ' . $item['Null'] . ' | ' . ($item['Extra'] == 'auto_increment' ? '是' : '') . ' | ' . (empty($item['Comment']) ? '-' : str_replace('|', '/', $item['Comment'])) . ' |' . PHP_EOL;
            }
            if (!empty($val['INDEX'])) {
                $mark .= '|  索引名  |  索引顺序  |' . PHP_EOL;
                $mark .= '| ------ |------ |' . PHP_EOL;
                foreach ($val['INDEX'] as $indexName => $indexContent) {
                    $mark .= '| ' . $indexName . ' | ' . implode(' > ', $indexContent) . ' | ' . PHP_EOL;
                }
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

    /**
     * 指定单个表导出
     * @param $table
     * @return $this
     */
    public function setExportTable($table)
    {
        $this->exportTables[] = $table;
        return $this;
    }

    /**
     * 指定多个表导出
     * @param $arr
     * @return $this
     */
    public function setExportTables($arr)
    {
        $this->exportTables = $arr;
        return $this;
    }

    public function generatePdf($dest='I')
    {
        $this->common();
        $title = $this->database . '数据字典';
        $html = '<!DOCTYPE html>
              <html>
              <meta charset="utf-8">
              <title>' . $title . '数据字典</title>
                <style>
                body,td,th {font-family:"宋体"; font-size:12px;}
                table{border-collapse:collapse;border:1px solid #CCC;background:#6089D4;}
                table caption{text-align:left; background-color:#fff; line-height:2em; font-size:14px; font-weight:bold; }
                table td{height:25px; font-size:12px; border:3px solid #fff; background-color:#f0f0f0; padding:5px;}
                tr:hover td{ background-color:#f1f5fb; }
                .c1{ width: 150px;}
                .c2{ width: 130px;}
                .c3{ width: 70px;}
                .c4{ width: 80px;}
                .c5{ width: 80px;}
                .c6{ width: 300px;}
                .c7{ width: 200px;}
                .c8{ width: 515px;}
                </style>
                </head>
                <body>';
        $html .= '<h1 style="text-align:center;">' . $title . '</h1>';
        $html .= '<header><p style="text-align:center;margin:20px auto;">总共：' . count($this->tables) . '个数据表' . "&nbsp" . '生成时间：' . date('Y-m-d H:i:s') . '</p></header>';
        $html .= $this->htmlTable;
        $html .= '</body></html>';
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($title);
        $pdf->SetTitle($title);

        $pdf->setPrintHeader();
        $pdf->setPrintFooter();

        // set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // set margins
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Add a page
        // This method has several options, check the source code documentation for more information.
        $pdf->AddPage();

        // set default font subsetting mode
        $pdf->setFontSubsetting(true);
        // Set font
        // dejavusans is a UTF-8 Unicode font, if you only need to
        // print standard ASCII chars, you can use core fonts like
        // helvetica or times to reduce file size.
        $pdf->SetFont('msungstdlight', '', 11, '', true);

        // set text shadow effect
        $pdf->setTextShadow(array('enabled' => false));
        // Set some content to print
        $pdf->writeHTML($html, true, false, true, false, '');
        // reset pointer to the last page
        $pdf->lastPage();
        // ---------------------------------------------------------
        // Close and output PDF document
        // This method has several options, check the source code documentation for more information.
        $pdf->Output($title . '.pdf', $dest);
    }

    public function __destruct()
    {
        $this->db = null;
    }
}