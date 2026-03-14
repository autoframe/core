<?php
declare(strict_types=1);


namespace Autoframe\Core\Captcha\Classic;


class AfrCaptchaClassicImgV2 extends AfrCaptchaClassicImg
{

    protected int $ImgVersion = 2;

    function getHtmlCaptcha(): string
    {

        return 'alfa 1.2';
    }


    /**
     * Create image.
     */
    public function createImage(string $sCode)
    {
        $iNbBgColors = 5;
        $iNbRectangles = 10;
        $iColourRangeStart = 125;
        $iColourRangeEnd = 175;
        $aBgColors = $aTextColors = [];


        $iCodeLength = strlen($sCode);
        list(
            $iWidth,
            $iHeight,
            $iFontSize
            ) = $this->getImageParameters($iCodeLength, 0.8);

        $this->initImage($iWidth, $iHeight, true);


        $this->imageFillColor($this->imagecolorallocateRandRGB($iColourRangeStart,$iColourRangeEnd));

        for ($i = 0; $i < $iNbBgColors; $i++) {
            $aBgColors[] = $this->imagecolorallocateRandRGB($iColourRangeStart,$iColourRangeEnd);
        }

        for ($i = 0; $i < $iNbRectangles; $i++) {
            $this->imageRandRectangle($this->image, $aBgColors[mt_rand(0, count($aBgColors)-1)], 0.03,0.07,0.15);
        }

        for ($i = 0; $i < $iCodeLength; $i++) {
            if(rand(0,99)%2){
                $iTextColourRangeStart = 20;
                $iTextColourRangeEnd = $iColourRangeStart-10;
            }
            else{
                $iTextColourRangeStart = $iColourRangeEnd+10;
                $iTextColourRangeEnd = 235;
            }
            $aTextColors[] = $this->imagecolorallocateRandRGB($iTextColourRangeStart,$iTextColourRangeEnd);
        }

        $this->writeCaptchaTextOnImage($sCode, $iFontSize, $aTextColors);

        $this->generateRandomDots($this->image, $aTextColors[rand(0, count($aTextColors)-1)], $iWidth, $iHeight, $this->fFontFactor * 0.17);
    }


}
