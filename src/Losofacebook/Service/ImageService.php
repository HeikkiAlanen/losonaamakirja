<?php

namespace Losofacebook\Service;
use Doctrine\DBAL\Connection;
use Imagick;
use ImagickPixel;
use Symfony\Component\HttpFoundation\Response;
use Losofacebook\Image;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// työkaluja optipng jpegoptim libjpeg-progs

/**
 * Image service
 */
class ImageService
{
    const COMPRESSION_TYPE = Imagick::COMPRESSION_JPEG;

    /**
     * @var Connection
     */
    private $conn;



    /**
     * @param $basePath
     */
    public function __construct(Connection $conn, $basePath)
    {
        $this->conn = $conn;
        $this->basePath = $basePath;
    }

    /**
     * Creates image
     *
     * @param string $path
     * @param int $type
     * @return integer
     */
    public function createImage($path, $type)
    {
        $this->conn->insert(
            'image',
            [
                'upload_path' => $path,
                'type' => $type
            ]
        );
        $id = $this->conn->lastInsertId();

        $img = new Imagick($path);
        $img->setbackgroundcolor(new ImagickPixel('white'));
        $img = $img->flattenImages();

        $img->setImageFormat("jpeg");

        $img->setImageCompression(self::COMPRESSION_TYPE);
        $img->setImageCompressionQuality(90);
        $img->scaleImage(1200, 1200, true);
        $img->writeImage($this->basePath . '/' . $id);

        if ($type == Image::TYPE_PERSON) {
            $this->createVersions($id);
        } else {
            $this->createCorporateVersions($id);
        }
        return $id;
    }


    public function createCorporateVersions($id)
    {
        $img = new Imagick($this->basePath . '/' . $id);
        $img->thumbnailimage(450, 450, true);

        $geo = $img->getImageGeometry();

        $x = (500 - $geo['width']) / 2;
        $y = (500 - $geo['height']) / 2;

        $image = new Imagick();
        $image->newImage(500, 500, new ImagickPixel('white'));
        $image->setImageFormat('jpeg');
        $image->compositeImage($img, $img->getImageCompose(), $x, $y);

        $thumb = clone $image;
        $thumb->cropThumbnailimage(360, 360);
        $thumb->setImageCompression(self::COMPRESSION_TYPE);
        $thumb->setImageCompressionQuality(70);
        $thumb->stripImage();
        $thumb->writeImage($this->basePath . '/' . $id . '-thumb');
    }


    public function createVersions($id)
    {
        $img = new Imagick($this->basePath . '/' . $id);

        // Person thumbnails
        $thumb = clone $img;
        $thumb->stripImage();
        $thumb->cropThumbnailimage(50, 50);
        $thumb->setImageCompression(self::COMPRESSION_TYPE);
        $thumb->setImageCompressionQuality(40);
        $thumb->writeImage($this->basePath . '/' . $id . '-thumb');

        // Post author image
        $thumb = clone $img;
        $thumb->stripImage();
        $thumb->cropThumbnailimage(75, 75);
        $thumb->setImageCompression(self::COMPRESSION_TYPE);
        $thumb->setImageCompressionQuality(50);
        $thumb->writeImage($this->basePath . '/' . $id . '-mini');

        // Person profile images
        $thumb = clone $img;
        $thumb->stripImage();
        $thumb->cropThumbnailimage(157, 157);
        $thumb->setImageCompression(self::COMPRESSION_TYPE);
        $thumb->setImageCompressionQuality(50);
        $thumb->writeImage($this->basePath . '/' . $id . '-profile');

        }

    public function getImageResponse($id, $version = null)
    {
        $path = $this->basePath . '/' . $id;

        if ($version) {
            $path .= '-' . $version;
//            console.debug($path);
        }

        if (!is_readable($path)) {
            throw new NotFoundHttpException('Image not found');
        }

        $response = new Response();
        $response->setContent(file_get_contents($path));
        $response->headers->set('Content-type', 'image/jpeg');
        return $response;
    }


}
