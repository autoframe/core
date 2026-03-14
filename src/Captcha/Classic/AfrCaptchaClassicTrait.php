<?php

namespace Autoframe\Core\Captcha\Classic;

use Autoframe\Core\Image\AfrImageTrait;

trait AfrCaptchaClassicTrait
{
    use AfrImageTrait;

    /**
     * @param $image
     * @param int $imageColorAllocate
     * @param int $iWidth
     * @param int $iHeight
     * @param float $fFontFactor
     * @param float $factorLevel
     * @param float $fThicknessFactor
     * @return void
     */
    protected function generateRandomDotLines($image, int $imageColorAllocate, int $iWidth, int $iHeight, float $fFontFactor = 1, float $factorLevel = 1, float $fThicknessFactor = 1)
    {
        $iSize = (int)(($iWidth + $iHeight) / 2);
        $fLinesNbWithSize = 1.5 / sqrt($iSize);
        for ($i = 0; $i < ($iSize * (0.1 + $fLinesNbWithSize) * $fFontFactor * $fFontFactor * $fFontFactor * $factorLevel); $i++) {
            $MinLineThickness = max(1, floor($iSize * $fThicknessFactor / 75));
            $iMaxLineThickness = max($MinLineThickness, floor($iSize * $fThicknessFactor / 40));
            $this->imagelinethick(
                $image,
                mt_rand(0, $iWidth),
                mt_rand(0, $iHeight),
                mt_rand(0, $iWidth),
                mt_rand(0, $iHeight),
                $imageColorAllocate,
                rand(rand(1, $MinLineThickness), $iMaxLineThickness)
            );
        }
    }

    /**
     * Random dots
     * @param $image
     * @param int $imageColorAllocate
     * @param int $iWidth
     * @param int $iHeight
     * @param float|int $factorLevel
     */
    protected function generateRandomDots($image, int $imageColorAllocate, int $iWidth, int $iHeight, float $factorLevel = 1)
    {
        for ($i = 0; $i < ($iWidth * $iHeight) * (($iWidth + $iHeight) / $iHeight) / 15 * $factorLevel; $i++) {
            imagefilledellipse(
                $image,
                mt_rand(0, $iWidth),
                mt_rand(0, $iHeight),
                rand(1, ceil($iHeight * (0.01))),
                rand(1, ceil($iHeight * (0.01))),
                $imageColorAllocate
            );
        }
    }

    /**
     * Hex color to dec.
     * @param string $color
     * @return array
     */
    public function hexColorToDec(string $color = '#000000'): array
    {
        if (substr($color, 0, 1) != '#') {
            return [];
        }
        $color = trim($color, '#-+.,');
        if (!is_numeric($color)) {
            return [];
        }

        if (strlen($color) == 6) {
            return [
                hexdec(substr($color, 0, 2)),
                hexdec(substr($color, 2, 2)),
                hexdec(substr($color, 4, 2))
            ];
        } elseif (strlen($color) == 3) {
            return [
                hexdec(substr($color, 0, 1) . substr($color, 0, 1)),
                hexdec(substr($color, 1, 1) . substr($color, 1, 1)),
                hexdec(substr($color, 2, 1) . substr($color, 2, 1))
            ];
        }
        return [];
    }

    /**
     * @param float $fPercent
     * @return int
     */
    protected function getIWidthTextStart(float $fPercent = 0.07): int
    {
        return floor(imagesx($this->image) * $fPercent);
    }

    /**
     * @param int $iCodeLength
     * @param $fPercent
     * @return int
     */
    protected function getILetterSpace(int $iCodeLength, $fPercent = 0.885): int
    {
        return floor(imagesx($this->image) * $fPercent / $iCodeLength);
    }



    /**
     * @param string $sCode
     * @param int $iFontSize
     * @param array $aTextColors
     * @return void
     */
    protected function writeCaptchaTextOnImage(string $sCode, int $iFontSize, array $aTextColors)
    {
        $iCodeLength = strlen($sCode);
        $iHeight = imagesy($this->image);
        $iMaxTextAngle = $this->maxTextAngle();

        $iXStart = $this->getIWidthTextStart();
        $iXEnd = $this->getILetterSpace($iCodeLength);
        for ($i = 0; $i < $iCodeLength; $i++) {
            imagettftext(
                $this->image,
                $iFontSize - ceil($iFontSize * 0.1),
                rand(-$iMaxTextAngle, $iMaxTextAngle),
                $iXStart + $i * $iXEnd,
                rand(ceil($iHeight * 0.67), floor($iHeight * 0.87)),
                $aTextColors[$i],
                $this->sFontFile,
                $sCode[$i]
            );
        }
    }

    /**
     * @param string $sCode
     * @param int $iFontSize
     * @param array $aTextColors
     * @return void
     */
    protected function writeFakeCaptchaTextOnImage(string $sCode, int $iFontSize, array $aTextColors)
    {
        $iCodeLength = strlen($sCode);
        if ($iCodeLength < 6) {
            $sCode = substr($sCode . $sCode . $sCode, 0, 6);
            $iCodeLength = strlen($sCode);
        }
        $iWidth = imagesx($this->image);
        $iHeight = imagesy($this->image);
        $iMaxTextAngle = $this->maxTextAngle();

        $iXStart = floor(-$iWidth * 0.15);
        $iXEnd = floor($iWidth * 1.15 / $iCodeLength);
        for ($i = 0; $i < $iCodeLength; $i++) {
            //imagettftext ($image, $size, $angle, $x, $y, $color, $fontfile, $text)
            imagettftext(
                $this->image,
                ceil($iFontSize * rand(185, 266) / 100),
                rand(-$iMaxTextAngle, $iMaxTextAngle),
                $iXStart + $i * $iXEnd,
                rand(ceil($iHeight * 0.85), floor($iHeight * 1.07)),
                $aTextColors[$i % count($aTextColors)],
                $this->sFontFile,
                $sCode[$i]
            );
        }
    }

    /**
     * @param $image
     * @param int $lineColor
     * @param float $fThicknessFrom
     * @param float $fThicknessTo
     * @param float $fRectangleOffsetDelta
     * @return bool
     */
    protected function imageRandRectangle(
        $image,
        int $lineColor,
        float $fThicknessFrom = 0.03,
        float $fThicknessTo = 0.07,
        float $fRectangleOffsetDelta = 0.15
    ): bool
    {
        $iWidth = imagesx($image);
        $iHeight = imagesy($image);

        $iWHSizeAvg = (int)(($iWidth + $iHeight) / 2);
        $iThicknessFrom = round($iWHSizeAvg * $fThicknessFrom);
        $iThicknessTo = round($iWHSizeAvg * $fThicknessTo);
        $iRectangleOffsetDelta = round($iWHSizeAvg * $fRectangleOffsetDelta);

        imagesetthickness(
            $image,
            rand($iThicknessFrom, $iThicknessTo)
        );
        // imagerectangle ( resource $image , int $x1 , int $y1 , int $x2 , int $y2 , int $color )
        // x1 Upper left x coordinate. y1 Upper left y coordinate 0, 0 is the top left corner of the image. x2 Bottom right x coordinate. y2 Bottom right y coordinate.
        return imagerectangle(
            $image,
            rand(-$iRectangleOffsetDelta, $iWidth - $iRectangleOffsetDelta),
            rand(-$iRectangleOffsetDelta, $iRectangleOffsetDelta),
            rand(-$iRectangleOffsetDelta, $iWidth + $iRectangleOffsetDelta),
            rand($iHeight - $iRectangleOffsetDelta, $iHeight + $iRectangleOffsetDelta),
            $lineColor
        );
    }

    /**
     * @param $image
     * @param int $lineColor
     * @param int $iWidth
     * @param int $iHeight
     * @param int $iFontSize
     * @param float $factorLevel
     * @param int $iCoveragePercent
     * @return void
     */
    protected function arcLines($image, int $lineColor, int $iWidth, int $iHeight, int $iFontSize, float $factorLevel = 1, int $iCoveragePercent = 80)
    {
        $iThickness = ceil($iFontSize / rand(15, 20) * $factorLevel);
        imagesetthickness($image, $iThickness);

        $iCoveragePercent = max(15, abs($iCoveragePercent));
        $iCoveragePercent = min(150, $iCoveragePercent);

        $iAspectBase = round(min($iWidth, $iHeight) * $iCoveragePercent / 100);
        $iPadding = round((min($iWidth, $iHeight) - $iAspectBase) / 2);

        $aRatios = [0.65, 0.725, 0.8];
        $iEllipseW = round(rand((int)($iAspectBase * $aRatios[rand(0, 2)]), $iAspectBase));
        $iEllipseH = round(rand((int)($iAspectBase * $aRatios[rand(0, 2)]), $iAspectBase));

        $iEllipseCenterX = rand(
            round($iPadding + $iEllipseW / 2),
            round($iWidth - $iPadding - $iEllipseW / 2)
        );

        $iEllipseCenterY = rand(
            round($iPadding + $iEllipseH / 2),
            round($iHeight - $iPadding - $iEllipseH / 2)
        );

        $iRandArcStart = rand(0, 100);
        if ($iRandArcStart < 20) {
            $iArcStart = rand(0, 359);
            $iArcEnd = $iArcStart + rand(65, 140);
        } elseif ($iRandArcStart % 2) {
            $iArcStart = rand(15, 65);
            $iArcEnd = $iArcStart + rand(75, 120);
        } else {
            $iArcStart = rand(195, 245);
            $iArcEnd = $iArcStart + rand(75, 120);
        }

        imagearc($image, $iEllipseCenterX, $iEllipseCenterY, $iEllipseW, $iEllipseH, $iArcStart, $iArcEnd, $lineColor);
    }

    /**
     * @param $image
     * @param int $lineColor
     * @param int $iWidth
     * @param int $iHeight
     * @param string $sPattern
     * @param float $factorLevel
     * @return void
     */
    protected function drawLines($image, int $lineColor, int $iWidth, int $iHeight, string $sPattern = '|-\/', float $factorLevel = 1)
    {
        $iLineDistance = ceil(min($iWidth, $iHeight) / 10 * $factorLevel);
        $iThickness = ceil($iLineDistance * 0.1);
        imagesetthickness($image, $iThickness);


        if (strpos($sPattern, '|') !== false || strpos($sPattern, '+') !== false) {
            //vertical lines
            for ($x = $iLineDistance; $x < $iWidth; $x += $iLineDistance) {
                imageline($image, $x, 0, $x, $iHeight, $lineColor);
            }
        }
        if (strpos($sPattern, '-') !== false || strpos($sPattern, '+') !== false) {
            //horizontal lines
            for ($y = $iLineDistance; $y < $iHeight; $y += $iLineDistance) {
                imageline($image, 0, $y, $iWidth, $y, $lineColor);
            }
        }

        if (strpos($sPattern, '\\') !== false || strpos(strtolower($sPattern), 'x') !== false) {
            $start = -($iHeight - ($iHeight % $iLineDistance));
            for ($x = $start; $x < $iWidth; $x += $iLineDistance) {
                imageline($image, $x, 0, $x + $iHeight, $iHeight, $lineColor);
            }
        }

        if (strpos($sPattern, '/') !== false || strpos(strtolower($sPattern), 'x') !== false) {
            $start = ($iWidth + $iHeight) - (($iWidth + $iHeight) % $iLineDistance);
            for ($x = $start; $x > 0; $x -= $iLineDistance) {
                imageline($image, $x, 0, $x - $iHeight, $iHeight, $lineColor);
            }
        }
    }

}
