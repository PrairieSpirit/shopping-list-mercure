<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateItemDto;
use App\DTO\UpdateItemDto;
use App\Entity\Item;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class ItemService
{
    /** Mercure topic — must match what the browser subscribes to */
    private const TOPIC = 'https://shopping-list/items';

    public function __construct(
        private readonly ItemRepository         $itemRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HubInterface           $hub,
    ) {}

    /** @return Item[] */
    public function findAll(): array
    {
        return $this->itemRepository->findAllOrderedByDate();
    }

    /** @return Item[] */
    public function findSince(\DateTimeImmutable $since): array
    {
        return $this->itemRepository->findUpdatedSince($since);
    }

    public function findOneOrFail(int $id): Item
    {
        $item = $this->itemRepository->find($id);

        if ($item === null) {
            throw new \InvalidArgumentException(
                sprintf('Item with id "%d" not found', $id)
            );
        }

        return $item;
    }

    public function create(CreateItemDto $dto): Item
    {
        $item = (new Item())->setText($dto->text);

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        $this->publish('item.created', $item->toArray());

        return $item;
    }

    public function update(int $id, UpdateItemDto $dto): Item
    {
        $item = $this->findOneOrFail($id);

        if ($dto->text !== null) {
            $item->setText($dto->text);
        }
        if ($dto->isDone !== null) {
            $item->setIsDone($dto->isDone);
        }

        $this->entityManager->flush();

        $this->publish('item.updated', $item->toArray());

        return $item;
    }

    public function delete(int $id): void
    {
        $item = $this->findOneOrFail($id);

        $this->entityManager->remove($item);
        $this->entityManager->flush();

        $this->publish('item.deleted', ['id' => $id]);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function publish(string $type, array $data): void
    {
        $this->hub->publish(new Update(
            topics:  self::TOPIC,
            data:    json_encode(['type' => $type, 'data' => $data]),
            private: false,  // anonymous subscribers allowed
        ));
    }
}
