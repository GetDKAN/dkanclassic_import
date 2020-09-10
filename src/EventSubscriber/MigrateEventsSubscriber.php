<?php

namespace Drupal\dkanclassic_import\EventSubscriber;

use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Class MigrateEventsSubscriber.
 *
 * Run our user flagging after the last node migration is run.
 *
 * @package Drupal\YOUR_MODULE
 */
class MigrateEventsSubscriber implements EventSubscriberInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * Node storage service.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  private $nodeStorage;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private $logger;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   Injected entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logger service.
   */
  public function __construct(EntityTypeManager $entityTypeManager, LoggerChannelFactoryInterface $loggerFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->logger = $loggerFactory->get('dkanclassic_import');
  }

  /**
   * Get subscribed events.
   *
   * @inheritdoc
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_ROW_SAVE][] = ['onMigratePostRowSave'];
    return $events;
  }

  /**
   * Update dataset autorship after our last migration is run.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The import event object.
   */
  public function onMigratePostRowSave(MigratePostRowSaveEvent $event) {
    if ($event->getMigration()->getBaseId() == 'dkanclassic_users') {
      $row = $event->getRow();
      $uid = $row->get('uid');
      $uuids = $row->get('uuids');

      $missing_uuids = [];
      $updated_uuids = [];

      if (!empty($uuids)) {
        foreach (explode(',', $uuids) as $uuid) {
          $nids = $this->nodeStorage->getQuery()
            ->condition('uuid', $uuid)
            ->condition('type', 'data')
            ->condition('field_data_type', 'dataset')
            ->execute();
          if (empty($nids)) {
            $missing_uuids[] = $uuid;
          }
          else {
            foreach ($nids as $nid) {
              $this->nodeStorage->load($nid)
                ->setOwnerId($uid)
                ->setRevisionAuthorId($uid)
                ->save();

              $updated_uuids[] = $uuid;
            }
          }
        }

        // Log updated UUIDs.
        $updated_count = count($updated_uuids);
        if ($updated_count > 0) {
          $this->logger->debug("for user @uid, @count datasets were updated: @Updated_uuids.", [
            '@uid' => $uid,
            '@count' => $updated_count,
            '@Updated_uuids' => implode(', ', $updated_uuids),
          ]);
        }

        // Log missing UUIDs.
        $missing_count = count($missing_uuids);
        if ($missing_count > 0) {
          $this->logger->error("for user @uid, @count datasets were not found: @missing_uuids.", [
            '@uid' => $uid,
            '@count' => $missing_count,
            '@missing_uuids' => implode(', ', $missing_uuids),
          ]);
        }
      }
    }
  }
}
