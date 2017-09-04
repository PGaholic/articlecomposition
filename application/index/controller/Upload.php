<?php

namespace app\index\controller;

use think\Db;
use think\Request;

class Upload extends \think\Controller
{
    //上传11
    public function index()
    {
        return $this->fetch();
    }

    //生成文章页面
    public function create()
    {
        return $this->fetch();
    }

    //组合文章(多组关键词,每组生成1篇文章)
    public function handle_create_new()
    {
        $param = Request::instance()->param();
        $keyword_array = $param['keyword'];
        $new_keyword = array();
        foreach ($keyword_array as $v) {
            if (!empty($v)) {
                $keywords = explode('&', $v);
                if (count($keywords) > 5) {
                    $this->error('每组最多五个关键词', 'Upload/create');
                }
                $new_keyword[] = $keywords;
            }
        }
        if (!empty($new_keyword)) {
            //先清除$path下的所有文件(否则原来生成的文章会再次压进zip)
            $path = "./downloads/" . date('Y-m-d', time());
            if (!is_dir($path)) {
                $res = mkdir($path, 0777, true);
                if (!$res) {
                    $this->error('创健文件夹失败', 'Upload/create');
                }
            }
            $this->delDirAndFile($path);
            foreach ($new_keyword as $v1) {
                foreach ($v1 as $v) {
                    $title_array[] = db('article_title')->field("GROUP_CONCAT(id) as ids")->where(array(
                        'title'  => array(
                            'like',
                            "%" . $v . "%"
                        ),
                        'status' => 0
                    ))->find();
                    $content_array[] = db('article_content')->field("GROUP_CONCAT(id) as ids")->where(array(
                        'content' => array(
                            'like',
                            "%" . $v . "%"
                        ),
                        'status'  => 0
                    ))->find();
                }
//                print_r($title_array);
//                print_r($content_array);die;
                $_content = $this->composition($content_array);
                $_title = $this->composition($title_array, 'title');
                //开始生成文件
                $res = db('article_content')->field('content')->where(array('id' => $_content))->find();
                db("article_content")->where(array('id' => $_content))->update(array("status" => 1));
                //取标题
                if (empty($_title)) {
                    //没有匹配到标题,先取段落原本的标题
                    $_new_title = db('article_title')->field('title')->where(array(
                        'content_id' => $_content,
                        'status'     => 0
                    ))->find();
                    db('article_title')->where(array('content_id' => $_content))->update(array("status" => 1));
                    //如果还没取到,给默认标题
                    if (empty($_new_title)) {
                        $_new_title['title'] = "原本标题被过滤,请自定义";
                    }
                } else {
                    $_new_title = db('article_title')->field('title')->where(array('id' => $_title))->find();
                    db('article_title')->where(array('id' => $_title))->update(array("status" => 1));
                }
                //拼接文章
                $res = "<h1>" . $_new_title['title'] . "</h1>" . $res['content'];
//                $a = implode('&', $v1);
//                $fileName = $path . "/" . $a . ".txt";
//                if (PHP_OS == 'WINNT') {
//                    $fileName = iconv('UTF-8', 'GB2312', $fileName);
//                }
//                $f = fopen($fileName, "w");
                $f = fopen($path . "/" . date('YmdHis') . rand(0000, 9999) . ".txt", "w");
                fwrite($f, $res);
                fclose($f);
            }
            //压缩
            $datalist = $this->list_dir($path . "/");//获取文件列表
            $filename = $path . "/" . date("YmdHi") . ".zip"; //最终生成的文件名
            if (!file_exists($filename)) {
                //重新生成文件
                $zip = new \ZipArchive();//使用本类，linux需开启zlib，windows需取消php_zip.dll前的注释
                if ($zip->open($filename, \ZIPARCHIVE::CREATE) !== true) {
                    exit('无法打开文件，或者文件创建失败');
                }
                foreach ($datalist as $val) {
//                    $a = dirname(dirname(dirname(__DIR__))).'/public'.mb_substr($val,1);
//                    $val = str_replace('/', '\\', $a);
//                    $val=iconv('GB2312','UTF-8',$val);
                    if (file_exists($val)) {
//                        $a = iconv('UTF-8','GB2312' ,$val);
//                        echo $a;die;
//                        $a = iconv('UTF-8','GB2312' ,basename($val));
                        $zip->addFile($val, basename($val));//第二个参数是放在压缩包中的文件名称，如果文件可能会有重复，就需要注意一下
                    }
                }
                $zip->close();//关闭
            }
            if (!file_exists($filename)) {
                exit("生成压缩文件失败"); //即使创建，仍有可能失败
            }
            //下载
            ob_end_clean();
            header("Content-Type: application/force-download");
            header("Content-Transfer-Encoding: binary");
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename=' . basename($filename));
            header('Content-Length: ' . filesize($filename));
            error_reporting(0);
            readfile($filename);
            flush();
            ob_flush();
        } else {
            $this->error('请输入关键词', 'Upload/create');
        }

    }

    function addFileToZip($path, $zip)
    {
        $handler = opendir($path);
        while (($filename = readdir($handler)) !== false) {
            if ($filename != "." && $filename != "..") {
                //文件夹文件名字为'.'和‘..'，不要对他们进行操作
                if (is_dir($path . "/" . $filename)) {
                    // 如果读取的某个对象是文件夹，则递归
                    $this->addFileToZip($path . "/" . $filename, $zip);
                } else {
                    //将文件加入zip对象
                    $zip->addFile($path . "/" . $filename);
                }
            }
        }
        @closedir($path);
    }

//组合文章(一组关键词生成多篇文章)
//    public function handle_create()
//    {
//        header("Content-type: text/html;charset=utf-8");
//        $param = Request::instance()->param();
//        if (empty($param)) {
//            $this->error('请填写关键词', 'Upload/create');
//        }
//        $keyword_array = explode('&', $param['keyword']);
//        if (count($keyword_array) > 5) {
//            $this->error('最多五个关键词', 'Upload/create');
//        }
//        $number = ($param['number'] && is_numeric($param['number'])) ? $param['number'] : 10;
//        //匹配
//        foreach ($keyword_array as $v) {
//            $title_array[] = db('article_title')->field("GROUP_CONCAT(id) as ids")->where(array(
//                'title' => array(
//                    'like',
//                    "%" . $v . "%"
//                )
//            ))->find();
//            $content_array[] = db('article_content')->field("GROUP_CONCAT(id) as ids")->where(array(
//                'content' => array(
//                    'like',
//                    "%" . $v . "%"
//                ),
//                'status'  => 0
//            ))->find();
//        }
//
//        $_content = $this->composition($content_array, $number);
//        $_title = $this->composition($title_array, $number, 'title');
//        $path = "./downloads/" . date('Y-m-d', time());
//        if (!is_dir($path)) {
//            $res = mkdir($path, 0777, true);
//            if (!$res) {
//                echo "创建文件夹失败";
//                exit;
//            }
//        }
//
//        //先清除$path下的所有文件(否则原来生成的文章会再次压进zip)
//        $this->delDirAndFile($path);
//        //开始写入文件
//        if (is_array($_content)) {
//            foreach ($_content as $v) {
//                $res = db('article_content')->field('content')->where(array('id' => $v))->find();
//                //写入成功后,把内容软删除(后期这块加上事务处理)
//                db("article_content")->where(array('id' => $v))->update(array("status" => 1));
//                //取标题
//                if (is_array($_title) && !empty($_title)) {
//                    $_get_title = db('article_title')->field('title')->where(array('id' => (count($_title) > 1) ? array_shift($_title) : $_title[array_rand($_title)]))->find();
//                } elseif (is_array($_title) && empty($_title)) {
//                    //没匹配到任何标题
//                    $_get_title = db('article_title')->field('title')->where(array('content_id' => $v))->find();
//                    //可能存在一篇文章,内容插入了,但是标题因为重复过滤掉了,没插入,$_get_title查出来有可能为空
//                    $_get_title['title'] = empty($_get_title) ? "<h1>标题自拟</h1>" : $_get_title['title'];
//                } else {
//                    $_get_title = db('article_title')->field('title')->where(array('id' => $_title))->find();
//                }
//                //拼接标题
//                $new_title = "<h1>" . $_get_title['title'] . "</h1>";
//                $res = $new_title . $res['content'];
//                $f = fopen($path . "/" . date('YmdHis') . rand(0000, 9999) . ".txt", "w");
//                fwrite($f, $res);
//                fclose($f);
//            }
//        } else {
//            //仅匹配到一篇文章
//            $res = db('article_content')->field('content')->where(array('id' => $_content))->find();
//            db("article_content")->where(array('id' => $_content))->update(array("status" => 1));
//            //取标题
//            if (is_array($_title) && !empty($_title)) {
//                $_get_title = db('article_title')->field('title')->where(array('id' => (count($_title) > 1) ? array_shift($_title) : $_title[array_rand($_title)]))->find();
//            } elseif (is_array($_title) && empty($_title)) {
//                //没有匹配到任何标题
//                $_get_title = db('article_title')->field('title')->where(array('content_id' => $_content))->find();
//                $_get_title['title'] = empty($_get_title) ? "<h1>标题自拟</h1>" : $_get_title['title'];
//            } else {
//                $_get_title = db('article_title')->field('title')->where(array('id' => $_title))->find();
//            }
//            //拼接标题
//            $new_title = "<h1>" . $_get_title['title'] . "</h1>";
//            $res = $new_title . $res['content'];
//            $f = fopen($path . "/" . date('YmdHis') . rand(0000, 9999) . ".txt", "w");
//            fwrite($f, $res);
//            fclose($f);
//        }
//        $datalist = $this->list_dir($path . "/");//获取文件列表
//        $filename = $path . "/" . date("YmdHi") . ".zip"; //最终生成的文件名（中文关键词做名称会报错）
//        if (!file_exists($filename)) {
//            //重新生成文件
//            $zip = new \ZipArchive();//使用本类，linux需开启zlib，windows需取消php_zip.dll前的注释
//            if ($zip->open($filename, \ZIPARCHIVE::CREATE) !== true) {
//                exit('无法打开文件，或者文件创建失败');
//            }
//            foreach ($datalist as $val) {
//                if (file_exists($val)) {
//                    $zip->addFile($val, basename($val));//第二个参数是放在压缩包中的文件名称，如果文件可能会有重复，就需要注意一下
//                }
//            }
//            $zip->close();//关闭
//        }
//        if (!file_exists($filename)) {
//            exit("无法找到文件"); //即使创建，仍有可能失败
//        }
//        ob_end_clean();
//        header("Content-Type: application/force-download");
//        header("Content-Transfer-Encoding: binary");
//        header('Content-Type: application/zip');
//        header('Content-Disposition: attachment; filename=' . basename($filename));
//        header('Content-Length: ' . filesize($filename));
//        error_reporting(0);
//        readfile($filename);
//        flush();
//        ob_flush();
//    }

    //入库
    public function upload()
    {
        header("Content-type: text/html;charset=utf-8");
        // 获取表单上传文件
        $file = request()->file('image');
        if (empty($file)) {
            $this->error("请选择文件", 'Upload/index');
        }
        // 移动到框架应用根目录/public/uploads/ 目录下
        $info = $file->validate([
            'size' => config('upload_max_filesize') * 1024 * 1024,
            'ext'  => 'txt'
        ])->move(ROOT_PATH . 'public' . DS . 'uploads');
        if ($info) {
            // 成功上传后 获取上传信息
            $content = file_get_contents(ROOT_PATH . 'public' . DS . 'uploads' . DS . $info->getSaveName());
            $res = $this->isUTF8($content);
            if (!$res) {
                $this->error("请上传UTF-8的TXT文件", 'Upload/index');
            }
            $content_array = explode("<h1>", $content);
            array_shift($content_array);
            $a = db('article_content')->count();//插入前条数
            foreach ($content_array as $v) {
                $array = explode("</h1>", $v);
                if (is_array($array) && count($array) != 1) {
                    $title_result = db('article_title')->where(array('title' => trim($array[0])))->find();
                    if (empty($title_result)) {
                        $content_result = db('article_content')->where(array('content' => trim($array[1])))->find();
                        if (empty($content_result)) {
                            $_content_id = db('article_content')->insertGetId(array(
                                'content' => trim($array[1]),
                                'time'    => time()
                            ));
                        } else {
                            //获取重复段落的标题
                            $list[] = $array[0];
                        }
                        db('article_title')->insert(array(
                            "title"      => trim($array[0]),
                            "time"       => time(),
                            'content_id' => isset($_content_id) ? $_content_id : 0,
                        ));
                    } else {
                        $list[] = $array[0];
                    }
                }
//                else {
//                    //不合规范的文章不入库
//                    $this->error('请仔细核对上传文件的内容', 'Upload/index');
//                }
            }
            $b = db('article_content')->count();
            $count = $b - $a;
            $this->assign('count', $count);
            if (isset($list)) {
                $this->assign('list', $list);
            } else {
                $this->assign('list', 0);
            }
            return $this->fetch();
//            $this->success('上传成功,去重之后,总计插入' . ($b - $a) . '条数据', 'Upload/index');
        } else {
            $this->error($file->getError(), 'Upload/index');
        }
    }

    public function download()
    {
    }

//获取文件列表
    function list_dir($dir)
    {
        $result = array();
        if (is_dir($dir)) {
            $file_dir = scandir($dir);
            foreach ($file_dir as $file) {
                if ($file == '.' || $file == '..') {
                    continue;
                } elseif (is_dir($dir . $file)) {
                    $result = array_merge($result, list_dir($dir . $file . '/'));
                } else {
                    array_push($result, $dir . $file);
                }
            }
        }
        return $result;
    }

    /**
     * 删除目录及目录下所有文件或删除指定文件
     * @param str $path 待删除目录路径
     * @param int $delDir 是否删除目录，1或true删除目录，0或false则只删除文件保留目录（包含子目录）
     * @return bool 返回删除状态
     */
    function delDirAndFile($path, $delDir = false)
    {
        $handle = opendir($path);
        if ($handle) {
            while (false !== ($item = readdir($handle))) {
                if ($item != "." && $item != "..") {
                    is_dir("$path/$item") ? delDirAndFile("$path/$item", $delDir) : unlink("$path/$item");
                }
            }
            closedir($handle);
            if ($delDir) {
                return rmdir($path);
            }
        } else {
            if (file_exists($path)) {
                return unlink($path);
            } else {
                return false;
            }
        }
    }

//检测编码
    function isUTF8($str)
    {
        if ($str === mb_convert_encoding(mb_convert_encoding($str, "UTF-32", "UTF-8"), "UTF-8", "UTF-32")) {
            return true;
        } else {
            return false;
        }
    }

//组合文章
    function composition($array, $label = 'content')
    {
        foreach ($array as $v) {
            if (!empty($v['ids'])) {
                $new_content_array[] = $v['ids'];
            }
        }
        if (!isset($new_content_array) && $label == 'content') {
            $this->error("没匹配到任何内容", 'Upload/create');
        }
        //如果标题没匹配到
        if (!isset($new_content_array) && $label == 'title') {
            return array();
        }
        $content_array = explode(",", implode(",", $new_content_array));
        //出现的次数
        $content_array = array_count_values($content_array);
        //排列
        arsort($content_array);
        //开始组合
        $a = array_flip($content_array);
        return array_shift($a);
    }
}
