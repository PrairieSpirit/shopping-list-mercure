<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class ItemControllerTest extends WebTestCase
{
    private static KernelBrowser $client;
    private static EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::$client = static::createClient();
        self::$em     = static::getContainer()->get('doctrine')->getManager();
        self::$em->createQuery('DELETE FROM App\\Entity\\Item i')->execute();
        self::$em->clear();
    }

    // ── GET /api/items ────────────────────────────────────────────────────────

    public function testGetItemsReturnsEmptyList(): void
    {
        self::$client->request('GET', '/api/items');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = $this->json();
        self::assertEquals(0, $data['total']);
        self::assertEmpty($data['data']);
    }

    public function testGetItemsReturnsList(): void
    {
        $this->createItem('Sunscreen');
        $this->createItem('Passport');

        self::$client->request('GET', '/api/items');

        self::assertResponseIsSuccessful();
        $data = $this->json();
        self::assertEquals(2, $data['total']);
        self::assertCount(2, $data['data']);
    }

    // ── POST /api/items ───────────────────────────────────────────────────────

    public function testCreateItemSuccess(): void
    {
        self::$client->request('POST', '/api/items', [], [], [], json_encode(['text' => 'Sunscreen']));

        self::assertResponseStatusCodeSame(201);
        $item = $this->json();

        self::assertArrayHasKey('id', $item);
        self::assertEquals('Sunscreen', $item['text']);
        self::assertFalse($item['is_done']);
        self::assertArrayHasKey('created_at', $item);
        self::assertArrayHasKey('updated_at', $item);
    }

    public function testCreateItemEmptyTextFails(): void
    {
        self::$client->request('POST', '/api/items', [], [], [], json_encode(['text' => '']));

        self::assertResponseStatusCodeSame(400);
        $data = $this->json();
        self::assertEquals('Validation failed', $data['error']);
        self::assertArrayHasKey('text', $data['details']);
    }

    public function testCreateItemMissingTextFails(): void
    {
        self::$client->request('POST', '/api/items', [], [], [], json_encode([]));
        self::assertResponseStatusCodeSame(400);
    }

    public function testCreateItemTooLongTextFails(): void
    {
        self::$client->request('POST', '/api/items', [], [], [],
            json_encode(['text' => str_repeat('a', 501)]));
        self::assertResponseStatusCodeSame(400);
    }

    // ── PUT /api/items/{id} ───────────────────────────────────────────────────

    public function testUpdateItemText(): void
    {
        $created = $this->createItem('Sunscreen');

        self::$client->request('PUT', "/api/items/{$created['id']}",
            [], [], [], json_encode(['text' => 'SPF50 Sunscreen']));

        self::assertResponseIsSuccessful();
        self::assertEquals('SPF50 Sunscreen', $this->json()['text']);
    }

    public function testUpdateItemIsDone(): void
    {
        $created = $this->createItem('Passport');

        self::$client->request('PUT', "/api/items/{$created['id']}",
            [], [], [], json_encode(['is_done' => true]));

        self::assertResponseIsSuccessful();
        self::assertTrue($this->json()['is_done']);
    }

    public function testUpdateNonExistentItemReturns404(): void
    {
        self::$client->request('PUT', '/api/items/99999',
            [], [], [], json_encode(['text' => 'test']));
        self::assertResponseStatusCodeSame(404);
    }

    public function testUpdateItemEmptyTextFails(): void
    {
        $created = $this->createItem('Item');

        self::$client->request('PUT', "/api/items/{$created['id']}",
            [], [], [], json_encode(['text' => '']));
        self::assertResponseStatusCodeSame(400);
    }

    // ── DELETE /api/items/{id} ────────────────────────────────────────────────

    public function testDeleteItemSuccess(): void
    {
        $created = $this->createItem('Delete me');

        self::$client->request('DELETE', "/api/items/{$created['id']}");
        self::assertResponseStatusCodeSame(204);

        self::$client->request('GET', '/api/items');
        self::assertEquals(0, $this->json()['total']);
    }

    public function testDeleteNonExistentItemReturns404(): void
    {
        self::$client->request('DELETE', '/api/items/99999');
        self::assertResponseStatusCodeSame(404);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function json(): array
    {
        return json_decode(self::$client->getResponse()->getContent(), true);
    }

    private function createItem(string $text): array
    {
        self::$client->request('POST', '/api/items', [], [], [],
            json_encode(['text' => $text]));
        return $this->json();
    }
}
