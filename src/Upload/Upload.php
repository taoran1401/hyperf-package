<?php


namespace Taoran\HyperfPackage\Upload;

use Hyperf\Di\Annotation\Inject;

use Hyperf\HttpServer\Contract\RequestInterface;
use function Taoran\HyperfPackage\Helpers\get_msectime;


class Upload
{
    /**
     * @Inject()
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var string 上传目录
     */
    protected $uploadTmpDir = 'public/uploads/tmp/';

    /**
     * 上传到本地
     *
     * @param $upload_path 上传路径
     */
    public function toLocal($file, $filename = null)
    {
        $this->checkAndCreateTmpDir();
        $filename = $filename ?? $this->genFilename($file);
        $pathfull = $this->uploadTmpDir . $filename;
        $file->moveTo($pathfull);
        if (!$file->isMoved()) {
            throw new \Exception('文件上传失败!');
        }
        return $pathfull;
    }

    /**
     * 上传到alioss
     */
    public function toAlioss($file, $upload_remote_path, $filename = null)
    {
        //检查目录
        $this->checkAndCreateTmpDir();
        //文件名
        $filename = $filename ?? $this->genFilename($file);
        //完整路径
        $path = $this->uploadTmpDir . $filename;
        //先上传本地
        $this->toLocal($file, $this->uploadTmpDir, $filename);
        //上传alioss
        $uploadAli = new \Taoran\HyperfPackage\Upload\Aliyun\Upload();
        $uploadAli->uploadDirect($upload_remote_path . $filename, $path, 'public');
        $pathfull = config('aliyun.oss.bucket_domain') . '/' . $upload_remote_path . $filename;
        //清除临时文件
        @unlink($path);
        return $pathfull;
    }

    /**
     * 保存base64到文件
     *
     * @param $save_path
     * @throws \Exception
     */
    public function saveBase64ToLocal($filename, $base64, $path = false)
    {
        if ($path) {
            $this->setUploadTmp($path);
        }
        $this->checkAndCreateTmpDir();
        $pathfull = $this->uploadTmpDir . $filename;
        if (!preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64, $result)) {
            throw new \Exception('base64信息错误！');
        }
        if (!file_put_contents($pathfull, base64_decode(str_replace($result[1], '', $base64)))) {
            throw new \Exception('上传失败！');
        }
        return $pathfull;
    }

    /**
     * 保存base64到文件
     *
     * @param $save_path
     * @throws \Exception
     */
    public function saveBase64ToAlioss($filename, $base64, $upload_remote_path)
    {
        //上传到本地目录
        $path = $this->saveBase64ToLocal($filename, $base64);
        //上传alioss
        $uploadAli = new \Taoran\HyperfPackage\Upload\Aliyun\Upload();
        $uploadAli->uploadDirect($upload_remote_path . $filename, $path, 'public');
        $pathfull = config('aliyun.oss.bucket_domain') . '/' . $upload_remote_path . $filename;
        //清除临时文件
        @unlink($path);
        return $pathfull;
    }

    /**
     * 生成文件名称
     *
     * @return string
     */
    public function genFilename($file)
    {
        return get_msectime() . '.' . $file->getExtension();
    }

    /**
     * 获取上传时文件存储目录
     *
     * @return string
     */
    public function getUploadTmp()
    {
        return $this->uploadTmpDir;
    }

    /**
     * 设置上传时文件存储目录
     */
    public function setUploadTmp($dir)
    {
        if (!is_dir($dir)) {
            throw new \Exception('无效的目录！');
        }
        $this->uploadTmpDir = $dir;
    }

    /**
     * 检查并创建目录; 可使用setUploadTmp设置目录
     */
    public function checkAndCreateTmpDir()
    {
        if (!is_dir($this->uploadTmpDir)) {
            mkdir($this->uploadTmpDir, 0777, true);
        }
    }

    /**
     * check
     * @return \Hyperf\HttpMessage\Upload\UploadedFile|\Hyperf\HttpMessage\Upload\UploadedFile[]|null
     */
    public function checkFile()
    {
        if ($this->request->hasFile('file') && $this->request->file('file')->isValid()) {
            $file = $this->request->file('file');
        } else {
            throw new \Exception('文件上传失败!');
        }

        $this->extCheck($this->request->file('file')->getExtension());

        return $file;
    }

    /**
     * 文件格式验证
     *
     * @param $ext
     * @return bool
     * @throws Exception
     */
    public function extCheck($ext)
    {
        $ext = strtolower($ext);
        $exts = ExtGroup::$ext;
        if (!in_array($ext, $exts)) {
            throw new \Exception('不支持的文件类型！');
        }
        return true;
    }
}
