<?php

namespace Autoframe\Core\Image;

use Autoframe\Core\Image\Exception\AfrImageException;

trait AfrImageTrait
{
    /** @var resource|false|GDImage */
    protected $image;
    protected int $iImgWidth;
    protected int $iImgHeight;
    protected string $sFontFile = '';

    /**
     * Img height.
     * @param int $iImgHeight
     * @return int
     */
    public function imgHeight(int $iImgHeight = 0): int
    {
        if ($iImgHeight && $iImgHeight > 0) {
            $this->iImgHeight = $iImgHeight;
        }

        if (isset($this->iImgHeight)) {
            return $this->iImgHeight;
        }
        return 0;
    }

    /**
     * Img width.
     * @param int $iImgWidth
     * @return int
     */
    public function imgWidth(int $iImgWidth = 0): int
    {
        if ($iImgWidth && $iImgWidth > 0) {
            $this->iImgWidth = $iImgWidth;
        }

        if (isset($this->iImgWidth)) {
            return $this->iImgWidth;
        }
        return 0;
    }

    /**
     * @param int $iWidth
     * @param int $iHeight
     * @param bool $bTrueColor
     * @return void
     * @throws AfrImageException
     */
    protected function initImage(int $iWidth, int $iHeight, bool $bTrueColor = true)
    {
        $this->image = $bTrueColor ? imagecreatetruecolor($iWidth, $iHeight) : imagecreate($iWidth, $iHeight);
        if (!$this->image) {
            throw new AfrImageException('Cannot initialize new GD image stream');
        }
    }

    /**
     * Generate rand rgb.
     * @param float $min
     * @param float $max
     * @param bool $signed
     * @param array $aMergeWith
     * @param bool $sum
     * @return array
     * @throws AfrImageException
     */
    public function generateRandRGB(float $min = 12, float $max = 52, bool $signed = false, array $aMergeWith = [], bool $sum = true): array
    {
        $aRGB = [];
        for ($i = 0; $i < 3; $i++) {
            $iRand = mt_rand((int)$min, (int)$max);
            if ($signed) {
                $iRand = $iRand * (mt_rand(0, 1) * 2 - 1);
            }
            $aRGB[$i] = $iRand;
        }
        if ($aMergeWith) {
            return $this->sumRGBArrays($aRGB, $aMergeWith, $sum);
        }
        return $this->normalizeRGBArray($aRGB);
    }

    /**
     * Sum rgbarrays.
     * @param array $aRGB
     * @param array $aMergeWith
     * @param bool $sum
     * @return array
     * @throws AfrImageException
     */
    public function sumRGBArrays(array $aRGB = [], array $aMergeWith = [], bool $sum = true): array
    {
        if (count($aRGB) === 3 && count($aMergeWith) === 3) {
            $aRGBMerge = [];
            for ($i = 0; $i < 3; $i++) {
                $aRGBMerge[$i] = $aRGB[$i] + ($sum ? $aMergeWith[$i] : -$aMergeWith[$i]);
            }
            return $this->normalizeRGBArray($aRGBMerge);
        }
        throw new AfrImageException('Expected RGB Array input of 3 int elements ranged 0 to 255');
    }

    /**
     * Normalize rgbarray.
     * @param array $aRGB
     * @return array
     * @throws AfrImageException
     */
    public function normalizeRGBArray(array $aRGB = []): array
    {
        if (count($aRGB) === 3) {
            for ($i = 0; $i < 3; $i++) {
                $operated = intval($aRGB[$i]);
                $operated = min(255, $operated);
                $operated = max(0, $operated);
                $aRGB[$i] = $operated;
            }
            return $aRGB;
        }
        throw new AfrImageException('Expected RGB Array input of 3 int elements ranged 0 to 255');
    }

    /**
     * @param int $min
     * @param int $max
     * @return false|int
     * @throws AfrImageException
     */
    protected function imagecolorallocateRandRGB(int $min = 63, int $max = 191)
    {
        $aRand = $this->generateRandRGB($min, $max, false);
        return $this->imagecolorallocateAData($aRand);
    }

    /**
     * @param $iColor
     * @param int $iX
     * @param int $iY
     * @return bool
     */
    protected function imageFillColor($iColor, int $iX = 0, int $iY = 0): bool
    {
        return imagefill($this->image, $iX, $iY, $iColor);
    }

    /**
     * @param array $aData
     * @return false|int
     * @throws AfrImageException
     */
    protected function imagecolorallocateAData(array $aData)
    {
        if (count($aData) !== 3) {
            throw new AfrImageException('Expected 3 int RGB!');
        }
        return imagecolorallocate($this->image, $aData[0], $aData[1], $aData[2]);
    }


    /**
     * @param $image
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     * @param int $imageColorAllocate
     * @param float $thick
     * @return void
     */
    protected function imagelinethick($image, int $x1, int $y1, int $x2, int $y2, int $imageColorAllocate, float $thick = 1): void
    {
        $t = $thick / 2 - 0.5;
        if ($thick === 1) {
            imagesetthickness($image, 1);
            imageline($image, $x1, $y1, $x2, $y2, $imageColorAllocate);
        } elseif ($x1 == $x2 || $y1 == $y2) {
            imagefilledrectangle($image, round(min($x1, $x2) - $t), round(min($y1, $y2) - $t), round(max($x1, $x2) + $t), round(max($y1, $y2) + $t), $imageColorAllocate);
        } else {
            $divisor = $x2 - $x1;
            $k = ($y2 - $y1) / ($divisor ?: 1); //y = kx + q
            $a = $t / sqrt(1 + pow($k, 2));
            $points = array(round($x1 - (1 + $k) * $a), round($y1 + (1 - $k) * $a), round($x1 - (1 - $k) * $a), round($y1 - (1 + $k) * $a), round($x2 + (1 + $k) * $a), round($y2 - (1 - $k) * $a), round($x2 + (1 - $k) * $a), round($y2 + (1 + $k) * $a),);

            //imagefilledpolygon($image, $points, 4, $imageColorAllocate); // Deprecated: imagefilledpolygon(): Using the $num_points parameter is deprecated
            //imagepolygon($image, $points, 4, $imageColorAllocate); //Deprecated: imagepolygon(): Using the $num_points parameter is deprecated
            imagefilledpolygon($image, $points, $imageColorAllocate); // Deprecated: imagefilledpolygon(): Using the $num_points parameter is deprecated
            imagepolygon($image, $points, $imageColorAllocate); //Deprecated: imagepolygon(): Using the $num_points parameter is deprecated
        }
    }

    /**
     * PHP8 Depending on which version of the GD library PHP is using, when fontfile does not begin
     * with a leading / then .ttf will be appended to the filename and the library will attempt
     * to search for that filename along a library-defined font path.
     * @param string $sFontPathName
     * @param bool $bCheckIfFileExists
     * @return void
     * @throws AfrImageException
     */
    protected function setFont(string $sFontPathName, bool $bCheckIfFileExists = false): void
    {
        if ($bCheckIfFileExists) {
            if (
                !is_file($sFontPathName) &&
                !is_file(getenv('GDFONTPATH') . DIRECTORY_SEPARATOR . $sFontPathName)
            ) {
                throw new AfrImageException('The following font file was nat found: ' . $sFontPathName);
            }
        }
        $this->sFontFile = $sFontPathName;
    }

    /**
     * @param int $x
     * @param int $y
     * @param string $sText
     * @param float $fFontSize
     * @param int $iTextColorIndex
     * @param float $fAngle
     * @return array|false
     */
    protected function writeTextOnImage(
        int    $x,
        int    $y,
        string $sText,
        float  $fFontSize,
        int    $iTextColorIndex,
        float  $fAngle = 0
    )
    {
        return imagettftext(
            $this->image,
            $fFontSize,
            $fAngle,
            $x,
            $y,
            $iTextColorIndex,
            $this->sFontFile,
            $sText
        );

    }

}
