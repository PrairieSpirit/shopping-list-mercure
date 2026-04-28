<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\CreateItemDto;
use App\DTO\UpdateItemDto;
use App\Service\ItemService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * REST API for shopping list items.
 *
 * Real-time updates are handled by Mercure hub (pub/sub),
 * not by this controller. Flow:
 *   Browser → POST/PUT/DELETE → ItemController → ItemService
 *   ItemService → Mercure hub (HTTP publish)
 *   Mercure hub → Browser (SSE/WebSocket push)
 */
#[Route('/api/items', name: 'api_items_')]
class ItemController extends AbstractController
{
    public function __construct(
        private readonly ItemService        $itemService,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * GET /api/items
     * GET /api/items?since=<ISO8601>  — items updated after timestamp
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $sinceParam = $request->query->get('since');

        try {
            $items = $sinceParam
                ? $this->itemService->findSince(new \DateTimeImmutable($sinceParam))
                : $this->itemService->findAll();
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid "since" date format'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'data'  => array_map(fn ($item) => $item->toArray(), $items),
            'total' => count($items),
        ]);
    }

    /**
     * POST /api/items
     * Body: {"text": "Sunscreen"}
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $dto  = new CreateItemDto(text: trim((string) ($data['text'] ?? '')));

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(
                ['error' => 'Validation failed', 'details' => $this->formatErrors($errors)],
                Response::HTTP_BAD_REQUEST
            );
        }

        return $this->json($this->itemService->create($dto)->toArray(), Response::HTTP_CREATED);
    }

    /**
     * PUT /api/items/{id}
     * Body: {"text": "..."} or {"is_done": true} or both
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $dto = new UpdateItemDto(
            text:   isset($data['text'])    ? trim((string) $data['text']) : null,
            isDone: isset($data['is_done']) ? (bool) $data['is_done']     : null,
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(
                ['error' => 'Validation failed', 'details' => $this->formatErrors($errors)],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            return $this->json($this->itemService->update($id, $dto)->toArray());
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * DELETE /api/items/{id}
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $this->itemService->delete($id);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function formatErrors(ConstraintViolationListInterface $errors): array
    {
        $result = [];
        foreach ($errors as $error) {
            $result[$error->getPropertyPath()] = $error->getMessage();
        }
        return $result;
    }
}
