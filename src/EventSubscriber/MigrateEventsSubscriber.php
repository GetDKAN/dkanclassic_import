<?php
namespace Drupal\dkanclassic_import\EventSubscriber;

use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\user\Entity\User;
use Drupal\metastore\Storage\Data;
use Drupal\Core\Entity\EntityTypeManager;

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
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   Injected entity type manager.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
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
   * Check for our specified last node migration and run our flagging mechanisms.
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
        $count = 0;
        foreach (explode(',', $uuids) as $uuid) {
          $nids = $this->nodeStorage->getQuery()
                                    ->condition('uuid', $uuid)
                                    ->condition('type', 'data')
                                    ->condition('field_data_type', 'dataset')
                                    ->execute();
          if(empty($nids)) {
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
          \Drupal::logger('dkanclassic_import')->debug("for user @uid, @count datasets were updated: @Updated_uuids.", [
            '@uid' => $uid,
            '@count' => $updated_count,
            '@Updated_uuids' => implode(', ', $updated_uuids),
          ]);
        }

        // Log missing UUIDs.
        $missing_count = count($missing_uuids);
        if ($missing_count > 0) {
          \Drupal::logger('dkanclassic_import')->error("for user @uid, @count datasets were not found: @missing_uuids.", [
            '@uid' => $uid,
            '@count' => $missing_count,
            '@missing_uuids' => implode(', ', $missing_uuids),
          ]);
        }
      }
    }
  }
}
