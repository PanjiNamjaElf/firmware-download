<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\FirmwareCheckRequest;
use App\Service\FirmwareMatchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Firmware version check endpoint — mirrors the legacy ConnectedSiteController::softwareDownload().
 *
 * Always returns HTTP 200, including for validation errors and "not found" cases.
 * The frontend checks versionExist and msg, not the status code.
 */
#[Route('/api/carplay/software', name: 'api_carplay_software_')]
class FirmwareController extends AbstractController
{
    public function __construct(
        private readonly FirmwareMatchService $firmwareMatchService,
    ) {
        //
    }

    /**
     * Returns the appropriate firmware download info or status message for the given device.
     *
     * Accepts version, hwVersion (required), and mcuVersion (ignored, legacy compat).
     * Always HTTP 200 — see FirmwareMatchService::process() for the full response shapes.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    #[Route('/version', name: 'version', methods: ['POST'])]
    public function version(Request $request): JsonResponse
    {
        $dto = $this->buildDto($request);

        $payload = $this->firmwareMatchService->process($dto);

        return new JsonResponse($payload, Response::HTTP_OK);
    }

    /**
     * Builds the request DTO from POST parameters.
     *
     * Supports both form-encoded (legacy frontend) and JSON bodies.
     * Parity: the legacy controller only accepted form POST; JSON support is additive.
     */
    private function buildDto(Request $request): FirmwareCheckRequest
    {
        // Support both application/x-www-form-urlencoded and application/json.
        if ($this->isJsonRequest($request)) {
            $data = json_decode($request->getContent(), true) ?? [];

            return new FirmwareCheckRequest(
                version: trim((string) ($data['version'] ?? '')),
                hwVersion: trim((string) ($data['hwVersion'] ?? '')),
                mcuVersion: trim((string) ($data['mcuVersion'] ?? '')),
            );
        }

        return new FirmwareCheckRequest(
            version: trim((string) $request->request->get('version', '')),
            hwVersion: trim((string) $request->request->get('hwVersion', '')),
            mcuVersion: trim((string) $request->request->get('mcuVersion', '')),
        );
    }

    /**
     * Returns true if the Content-Type header indicates a JSON body.
     */
    private function isJsonRequest(Request $request): bool
    {
        $haystack = $request->headers->get('Content-Type', '');

        if ($haystack !== '') {
            foreach (['/json', '+json'] as $needle) {
                if (str_contains($haystack, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }
}
