<?php

namespace MillenniumFalcon\Core\Asset;

use MillenniumFalcon\Core\Orm\Asset;
use MillenniumFalcon\Core\Orm\AssetCrop;
use MillenniumFalcon\Core\Orm\AssetSize;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AssetController extends Controller
{
    /**
     * @Route("/downloads/assets/{assetCode}", name="asset_download", methods={"GET"})
     * @Route("/downloads/assets/{assetCode}/{fileName}", name="asset_download_filename", requirements={"fileName"=".*"}, methods={"GET"})
     */
    public function assetDownload($assetCode, $fileName = null)
    {
        $connection = $this->container->get('doctrine.dbal.default_connection');
        /** @var \PDO $pdo */
        $pdo = $connection->getWrappedConnection();

        /** @var Asset $asset */
        $asset = Asset::getByField($pdo, 'code', $assetCode);
        if (!$asset) {
            throw new NotFoundHttpException();
        }

        $response = $this->assetImage($assetCode);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $asset->getFileName());
        return $response;
    }

    /**
     * @Route("/images/assets/{assetCode}", name="asset_image_original", methods={"GET"})
     * @Route("/images/assets/{assetCode}/{assetSizeCode}", name="asset_image", methods={"GET"})
     * @Route("/images/assets/{assetCode}/{assetSizeCode}/{fileName}", name="asset_image_filename", methods={"GET"})
     */
    public function assetImage($assetCode, $assetSizeCode = null, $fileName = null)
    {
        $request = Request::createFromGlobals();
        $useWebp = in_array('image/webp', $request->getAcceptableContentTypes());

        $connection = $this->container->get('doctrine.dbal.default_connection');
        /** @var \PDO $pdo */
        $pdo = $connection->getWrappedConnection();

        /** @var Asset $asset */
        $asset = Asset::getByField($pdo, 'code', $assetCode);
        if (!$asset) {
            throw new NotFoundHttpException();
        }

        $uploadPath = static::getUploadPath();
        $fileType = $asset->getFileType();
        $fileName = $asset->getFileName();
        $fileSize = $asset->getFileSize();
        $fileExtension = $asset->getFileExtension();
        $fileLocation = $uploadPath . $asset->getFileLocation();

        if ($asset->getIsImage()) {
            if ($assetSizeCode) {
                /** @var AssetSize $assetSize */
                $assetSize = AssetSize::getByField($pdo, 'code', $assetSizeCode);
                if (!$assetSize) {
                    throw new NotFoundHttpException();
                }

                $cachedKey = static::getCacheKey($asset, $assetSize);
                $cachedFolder = static::getImageCachePath();
                if (!file_exists($cachedFolder)) {
                    mkdir($cachedFolder, 0777, true);
                }

                /** @var AssetCrop $assetCrop */
                $assetCrop = AssetCrop::data($pdo, array(
                    'whereSql' => 'm.assetId = ? AND m.assetSizeId = ?',
                    'params' => array($asset->getId(), $assetSize->getId()),
                    'limit' => 1,
                    'oneOrNull' => 1,
                ));

                if ('application/pdf' == $fileType) {

                } elseif ($useWebp) {
                    $thumbnail = "{$cachedFolder}webp-{$cachedKey}.webp";

                    $resizeCmd = "-resize {$assetSize->getWidth()} 0";
                    $cropCmd = '';
                    if ($assetCrop) {
                        $cropCmd = "-crop {$assetCrop->getX()} {$assetCrop->getY()} {$assetCrop->getWidth()} {$assetCrop->getHeight()}";
                    }
                    $command = getenv('CWEBP_CMD') . " $fileLocation {$cropCmd} {$resizeCmd} -o $thumbnail";

                } else {
                    $thumbnail = "{$cachedFolder}{$cachedKey}.{$asset->getFileExtension()}";
                    $resizeCmd = "-resize {$assetSize->getWidth()}";
                    $qualityCmd = "-quality 95";
                    $cropCmd = '';
                    if ($assetCrop) {
                        $cropCmd = "-crop {$assetCrop->getWidth()}x{$assetCrop->getHeight()}+{$assetCrop->getX()}+{$assetCrop->getY()}";
                    }
                    $command = getenv('CONVERT_CMD') . " $fileLocation {$qualityCmd} {$cropCmd} {$resizeCmd} $thumbnail";
                }

                if (!file_exists($thumbnail)) {
                    $returnValue = $this->generateOutput($command);
                }
            } else {
                $thumbnail = $fileLocation;
            }
        } else {
            $thumbnail = __DIR__ . '/no-preview-big1.jpg';
            $fileSize = 11042;
            $fileType = 'image/jpeg';
        }

        $date = new \DateTimeImmutable('@' . filectime($uploadPath));
        $saveDate = $date->setTimezone(new \DateTimeZone("GMT"))->format("D, d M y H:i:s T");
        $response = BinaryFileResponse::create($thumbnail, Response::HTTP_OK, [
            "content-length" => $fileSize,
            "content-type" => $fileType,
            "last-modified" => $saveDate,
            "etag" => '"' . sprintf("%x-%x", $date->getTimestamp(), $fileSize) . '"',
        ], true, null, false, true);
        return $response;
    }

    /**
     * @param $command
     * @param string $in
     * @param null $out
     * @return int
     */
    protected function generateOutput($command, &$in = '', &$out = null)
    {
        $logFolder = static::getImageCachePath();
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
            1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
            2 => array("file", $logFolder . 'error-output.txt', 'a') // stderr is a file to write to
        );

        $returnValue = -999;

        $process = proc_open($command, $descriptorspec, $pipes);
        if (is_resource($process)) {

            fwrite($pipes[0], $in);
            fclose($pipes[0]);

            $out = "";
            //read the output
            while (!feof($pipes[1])) {
                $out .= fgets($pipes[1], 4096);
            }
            fclose($pipes[1]);
            $returnValue = proc_close($process);
        }

        return $returnValue;
    }

    /**
     * @param Asset $asset
     * @param AssetSize $assetSize
     * @return string
     */
    static public function getCacheKey(Asset $asset, AssetSize $assetSize) {
        return "{$asset->getCode()}-{$assetSize->getCode()}-{$asset->getId()}-{$assetSize->getId()}";
    }

    /**
     * @return string
     */
    static public function getUploadPath() {
        return __DIR__ . '/../../../../../uploads/';
    }

    /**
     * @return string
     */
    static public function getImageCachePath() {
        return __DIR__ . '/../../../../../cache/image/';
    }
}