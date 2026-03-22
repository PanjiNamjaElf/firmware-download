<?php

namespace AppBundle\Controller\API;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ConnectedSiteController extends Controller
{
    public function softwareDownload(Request $request)
    {
        $version = $request->request->get('version');
        $mcuVersion = $request->request->get('mcuVersion');
        $hwVersion = $request->request->get('hwVersion');
        //echo $hwVersion;exit;
        if (empty($version)) {
            $response = ['msg' => 'Version is required'];

            return new JsonResponse($response, 200);
        }

        if (empty($hwVersion)) {
            $response = ['msg' => 'HW Version is required'];

            return new JsonResponse($response, 200);
        }

        // $patternST = '/^CPAA_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/';
        // $patternGD = '/^CPAA_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/';

        $patternST = '/^CPAA_[0-9]{4}\.[0-9]{2}\.[0-9]{2}(_[A-Z]+)?$/i';
        $patternGD = '/^CPAA_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}(_[A-Z]+)?$/i';

        // LCI hardware patterns
        $patternLCI_CIC = '/^B_C_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i';
        $patternLCI_NBT = '/^B_N_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i';
        $patternLCI_EVO = '/^B_E_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i';

        $hwVersionBool = false;
        $stBool = false;
        $gdBool = false;
        $isLCI = false;
        $lciHwType = '';

        if (preg_match($patternST, $hwVersion)) {
            $hwVersionBool = true;
            $stBool = true;
        }

        if (preg_match($patternGD, $hwVersion)) {
            $hwVersionBool = true;
            $gdBool = true;
        }

        if (preg_match($patternLCI_CIC, $hwVersion)) {
            $hwVersionBool = true;
            $isLCI = true;
            $lciHwType = 'CIC';
            $stBool = true;
        } elseif (preg_match($patternLCI_NBT, $hwVersion)) {
            $hwVersionBool = true;
            $isLCI = true;
            $lciHwType = 'NBT';
            $gdBool = true;
        } elseif (preg_match($patternLCI_EVO, $hwVersion)) {
            $hwVersionBool = true;
            $isLCI = true;
            $lciHwType = 'EVO';
            $gdBool = true;
        }

        if (! $hwVersionBool) {
            // echo 1;exit;
            $response = ['msg' => 'There was a problem identifying your software. Contact us for help.'];

            return new JsonResponse($response, 200);
        }

        $filePath = realpath($this->getParameter('kernel.project_dir')).DIRECTORY_SEPARATOR.'web/assets/carplay/assets/js/softwareversions.json';
        $fileContent = file_get_contents($filePath);
        $softwareVersions = json_decode($fileContent, true);

        if (strpos($version, 'v') === 0 || strpos($version, 'V') === 0) {
            $version = substr($version, 1);
        }

        $response = [];
        foreach ($softwareVersions as $key => $row) {
            if (strcasecmp($row['system_version_alt'], $version) === 0) {
                $isLCIEntry = (strpos($row['name'], 'LCI') === 0);

                // Standard HW must only match standard entries, LCI must only match LCI
                if ($isLCI !== $isLCIEntry) {
                    continue;
                }

                // For LCI, also check that the hardware type (CIC/NBT/EVO) matches the entry
                if ($isLCI && stripos($row['name'], $lciHwType) === false) {
                    continue;
                }

                if ($row['latest']) {
                    $response = [
                        'versionExist' => true,
                        'msg' => 'Your system is upto date!',
                        'link' => '',
                        'st' => '',
                        'gd' => ''
                    ];
                } else {
                    $stLink = '';
                    $gdLink = '';
                    if ($stBool) {
                        $stLink = $row['st'];
                    }

                    if ($gdBool) {
                        $gdLink = $row['gd'];
                    }

                    $latestMsg = $isLCI ? 'v3.4.4' : 'v3.3.7';
                    $response = [
                        'versionExist' => true,
                        'msg' => 'The latest version of software is '.$latestMsg.' ',
                        'link' => $row['link'],
                        'st' => $stLink,
                        'gd' => $gdLink
                    ];
                }
                break;
            }
        }

        if ($response) {
            return new JsonResponse($response, 200);
        }

        $response = [
            'versionExist' => false,
            'msg' => 'There was a problem identifying your software. Contact us for help.',
            'link' => '',
            'st' => '',
            'gd' => ''
        ];

        return new JsonResponse($response, 200);
    }
}
