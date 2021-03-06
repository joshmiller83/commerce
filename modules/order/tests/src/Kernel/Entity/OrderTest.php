<?php

namespace Drupal\Tests\commerce_order\Kernel\Entity;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\LineItem;
use Drupal\commerce_order\Entity\LineItemType;
use Drupal\commerce_price\Price;
use Drupal\commerce_store\Entity\Store;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\profile\Entity\Profile;

/**
 * Tests the Order entity.
 *
 * @coversDefaultClass \Drupal\commerce_order\Entity\Order
 *
 * @group commerce
 */
class OrderTest extends EntityKernelTestBase {

  /**
   * A sample user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * A sample store.
   *
   * @var \Drupal\commerce_store\Entity\StoreInterface
   */
  protected $store;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'options',
    'entity',
    'views',
    'address',
    'profile',
    'state_machine',
    'inline_entity_form',
    'commerce',
    'commerce_price',
    'commerce_store',
    'commerce_product',
    'commerce_order',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('profile');
    $this->installEntitySchema('commerce_store');
    $this->installEntitySchema('commerce_order');
    $this->installEntitySchema('commerce_line_item');
    $this->installConfig('commerce_store');
    $this->installConfig('commerce_order');

    // A line item type that doesn't need a purchasable entity, for simplicity.
    LineItemType::create([
      'id' => 'test',
      'label' => 'Test',
      'orderType' => 'default',
    ])->save();

    $user = $this->createUser();
    $this->user = $this->reloadEntity($user);

    $store = Store::create([
      'type' => 'default',
      'name' => 'Sample store',
      'default_currency' => 'USD',
    ]);
    $store->save();
    $this->store = $this->reloadEntity($store);
  }

  /**
   * Tests the order entity and its methods.
   *
   * @covers ::getOrderNumber
   * @covers ::setOrderNumber
   * @covers ::getStore
   * @covers ::setStore
   * @covers ::getStoreId
   * @covers ::setStoreId
   * @covers ::getOwner
   * @covers ::setOwner
   * @covers ::getOwnerId
   * @covers ::setOwnerId
   * @covers ::getEmail
   * @covers ::setEmail
   * @covers ::getIpAddress
   * @covers ::setIpAddress
   * @covers ::getBillingProfile
   * @covers ::setBillingProfile
   * @covers ::getBillingProfileId
   * @covers ::setBillingProfileId
   * @covers ::getLineItems
   * @covers ::setLineItems
   * @covers ::hasLineItems
   * @covers ::addLineItem
   * @covers ::removeLineItem
   * @covers ::hasLineItem
   * @covers ::getAdjustments
   * @covers ::setAdjustments
   * @covers ::addAdjustment
   * @covers ::recalculateTotalPrice
   * @covers ::getTotalPrice
   * @covers ::getState
   * @covers ::getCreatedTime
   * @covers ::setCreatedTime
   * @covers ::getPlacedTime
   * @covers ::setPlacedTime
   */
  public function testOrder() {
    $profile = Profile::create([
      'type' => 'billing',
    ]);
    $profile->save();
    $profile = $this->reloadEntity($profile);

    /** @var \Drupal\commerce_order\Entity\LineItemInterface $line_item */
    $line_item = LineItem::create([
      'type' => 'test',
      'unit_price' => new Price('2.00', 'USD'),
    ]);
    $line_item->save();
    $line_item = $this->reloadEntity($line_item);
    /** @var \Drupal\commerce_order\Entity\LineItemInterface $another_line_item */
    $another_line_item = LineItem::create([
      'type' => 'test',
      'quantity' => '2',
      'unit_price' => new Price('3.00', 'USD'),
    ]);
    $another_line_item->save();
    $another_line_item = $this->reloadEntity($another_line_item);

    $order = Order::create([
      'type' => 'default',
      'state' => 'completed',
    ]);
    $order->save();

    $order->setOrderNumber(7);
    $this->assertEquals(7, $order->getOrderNumber());

    $order->setStore($this->store);
    $this->assertEquals($this->store, $order->getStore());
    $this->assertEquals($this->store->id(), $order->getStoreId());
    $order->setStoreId(0);
    $this->assertEquals(NULL, $order->getStore());
    $order->setStoreId([$this->store->id()]);
    $this->assertEquals($this->store, $order->getStore());
    $this->assertEquals($this->store->id(), $order->getStoreId());

    $order->setOwner($this->user);
    $this->assertEquals($this->user, $order->getOwner());
    $this->assertEquals($this->user->id(), $order->getOwnerId());
    $order->setOwnerId(0);
    $this->assertEquals(NULL, $order->getOwner());
    $order->setOwnerId($this->user->id());
    $this->assertEquals($this->user, $order->getOwner());
    $this->assertEquals($this->user->id(), $order->getOwnerId());

    $order->setEmail('commerce@example.com');
    $this->assertEquals('commerce@example.com', $order->getEmail());

    $order->setIpAddress('127.0.0.2');
    $this->assertEquals('127.0.0.2', $order->getIpAddress());

    $order->setBillingProfile($profile);
    $this->assertEquals($profile, $order->getBillingProfile());
    $this->assertEquals($profile->id(), $order->getBillingProfileId());
    $order->setBillingProfileId(0);
    $this->assertEquals(NULL, $order->getBillingProfile());
    $order->setBillingProfileId([$profile->id()]);
    $this->assertEquals($profile, $order->getBillingProfile());
    $this->assertEquals($profile->id(), $order->getBillingProfileId());

    $order->setLineItems([$line_item, $another_line_item]);
    $this->assertEquals([$line_item, $another_line_item], $order->getLineItems());
    $this->assertTrue($order->hasLineItems());
    $order->removeLineItem($another_line_item);
    $this->assertEquals([$line_item], $order->getLineItems());
    $this->assertTrue($order->hasLineItem($line_item));
    $this->assertFalse($order->hasLineItem($another_line_item));
    $order->addLineItem($another_line_item);
    $this->assertEquals([$line_item, $another_line_item], $order->getLineItems());
    $this->assertTrue($order->hasLineItem($another_line_item));

    $this->assertEquals(new Price('8.00', 'USD'), $order->getTotalPrice());
    $adjustments = [];
    $adjustments[] = new Adjustment([
      'type' => 'custom',
      'label' => '10% off',
      'amount' => new Price('-1.00', 'USD'),
    ]);
    $adjustments[] = new Adjustment([
      'type' => 'custom',
      'label' => 'Handling fee',
      'amount' => new Price('10.00', 'USD'),
    ]);
    $order->addAdjustment($adjustments[0]);
    $order->addAdjustment($adjustments[1]);
    $adjustments = $order->getAdjustments();
    $this->assertEquals($adjustments, $order->getAdjustments());
    $order->removeAdjustment($adjustments[0]);
    $this->assertEquals(new Price('18.00', 'USD'), $order->getTotalPrice());
    $this->assertEquals([$adjustments[1]], $order->getAdjustments());
    $order->setAdjustments($adjustments);
    $this->assertEquals($adjustments, $order->getAdjustments());
    $this->assertEquals(new Price('17.00', 'USD'), $order->getTotalPrice());
    // Add an adjustment to the second line item, confirm it's a part of the
    // order total, multiplied by quantity.
    $order->removeLineItem($another_line_item);
    $another_line_item->addAdjustment(new Adjustment([
      'type' => 'custom',
      'label' => 'Random fee',
      'amount' => new Price('5.00', 'USD'),
    ]));
    $order->addLineItem($another_line_item);
    $this->assertEquals(new Price('27.00', 'USD'), $order->getTotalPrice());

    $this->assertEquals('completed', $order->getState()->value);

    $order->setCreatedTime(635879700);
    $this->assertEquals(635879700, $order->getCreatedTime());

    $order->setPlacedTime(635879800);
    $this->assertEquals(635879800, $order->getPlacedTime());
  }

}
