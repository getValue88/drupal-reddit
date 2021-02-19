<?php

namespace Drupal\Tests\eme\Traits;

use Drupal\comment\Entity\Comment;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Trait for EME's functional tests.
 */
trait EmeTestTrait {

  use CommentTestTrait;

  /**
   * User "10" is the owner of node "2", node "3" and comment "2".
   *
   * @var array
   */
  protected $user10Data = [
    'uid' => 10,
  ];

  /**
   * User "20" is the owner of node "4" and comment "1".
   *
   * @var array
   */
  protected $user20Data = [
    'uid' => 20,
  ];

  /**
   * User "30" is the owner of node "1".
   *
   * @var array
   */
  protected $user30Data = [
    'uid' => 30,
  ];

  /**
   * Node "1" is a simple page and it does not have comments.
   *
   * @var array
   */
  protected $node1Data = [
    'nid' => 1,
    'uuid' => '4997f53d-62d0-4d0d-88fa-3e4ef1800282',
    'vid' => 1,
    'langcode' => 'en',
    'type' => 'page',
    'title' => 'Page 1',
    'revision_timestamp' => 1600000000,
    'revision_log' => 'Log for page 1',
    'status' => 1,
    'uid' => 30,
    'created' => 1600000000,
    'changed' => 1600000000,
    'promote' => 0,
    'sticky' => 0,
    'default_langcode' => 1,
    'revision_default' => 1,
    'revision_uid' => 30,
    'revision_translation_affected' => 1,
    'body' => [
      [
        'value' => 'Test body page 1',
        'summary' => 'Test body page 1',
        'format' => 'plain_text',
      ],
    ],
  ];

  /**
   * Node "2" is an article with two comments.
   *
   * @var array
   */
  protected $node2Data = [
    'nid' => 2,
    'uuid' => 'a47faf2f-71b4-4e2a-a50c-9d6ec80a6300',
    'vid' => 2,
    'langcode' => 'en',
    'type' => 'article',
    'title' => 'Article 2',
    'revision_timestamp' => 1600001000,
    'revision_log' => 'Log for article 2',
    'status' => 1,
    'uid' => 10,
    'created' => 1600001000,
    'changed' => 1600001000,
    'promote' => 1,
    'sticky' => 1,
    'default_langcode' => 1,
    'revision_default' => 1,
    'revision_uid' => 10,
    'revision_translation_affected' => 1,
    'body' => [
      [
        'value' => 'Test body article 2',
        'summary' => 'Test body article 2',
        'format' => 'plain_text',
      ],
    ],
  ];

  /**
   * Node "3" is an article without comments.
   *
   * @var array
   */
  protected $node3Data = [
    'nid' => 3,
    'uuid' => 'ab103d00-b2e9-4bdd-be43-312a5fa806b2',
    'vid' => 3,
    'langcode' => 'en',
    'type' => 'article',
    'title' => 'Article 3',
    'revision_timestamp' => 1600002000,
    'revision_log' => 'Log for article 3',
    'status' => 0,
    'uid' => 10,
    'created' => 1600002000,
    'changed' => 1600002000,
    'promote' => 1,
    'sticky' => 0,
    'default_langcode' => 1,
    'revision_default' => 1,
    'revision_uid' => 10,
    'revision_translation_affected' => 1,
    'body' => [
      [
        'value' => 'Test body article 3',
        'summary' => 'Test body article 3',
        'format' => 'plain_text',
      ],
    ],
  ];

  /**
   * Comment 1 (on node 2).
   *
   * @var array
   */
  protected $comment1Data = [
    'cid' => 1,
    'pid' => NULL,
    'uid' => 20,
    'entity_id' => 2,
    'entity_type' => 'node',
    'field_name' => 'comments',
    'comment_type' => 'article',
    'subject' => 'Comment 1 subject',
    'thread' => '00/',
    'comment_body' => [
      'value' => 'Comment 1 body',
      'format' => 'plain_text',
    ],
  ];

  /**
   * Comment 2 (on node 2, reply to comment 1).
   *
   * @var array
   */
  protected $comment2Data = [
    'cid' => 2,
    'pid' => 1,
    'uid' => 10,
    'entity_id' => 2,
    'entity_type' => 'node',
    'field_name' => 'comments',
    'comment_type' => 'article',
    'subject' => 'Reply',
    'thread' => '00.00/',
    'comment_body' => [
      'value' => 'Comment 2 body',
      'format' => 'plain_text',
    ],
  ];

  /**
   * Comment 3 (on node 2).
   *
   * @var array
   */
  protected $comment3Data = [
    'cid' => 3,
    'pid' => NULL,
    'uid' => 30,
    'entity_id' => 2,
    'entity_type' => 'node',
    'field_name' => 'comments',
    'comment_type' => 'article',
    'subject' => 'Comment 3 subject',
    'thread' => '01/',
    'comment_body' => [
      'value' => 'Comment 3 body',
      'format' => 'plain_text',
    ],
  ];

  /**
   * Node 1.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node1;

  /**
   * Node 2.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node2;

  /**
   * Node 3.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node3;

  /**
   * User 10.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user10;

  /**
   * User 20.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user20;

  /**
   * User 30.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user30;

  /**
   * Comment 1.
   *
   * @var \Drupal\comment\CommentInterface
   */
  protected $comment1;

  /**
   * Comment 2.
   *
   * @var \Drupal\comment\CommentInterface
   */
  protected $comment2;

  /**
   * Comment 3.
   *
   * @var \Drupal\comment\CommentInterface
   */
  protected $comment3;

  /**
   * Creates the entity types (node type, etc) for the tests.
   */
  protected function createTestEntityTypes() {
    // Setup a basic node.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);
    // Setup an article node type and add comment type.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->addDefaultCommentField('node', 'article', 'comments', CommentItemInterface::OPEN, 'article');
  }

  /**
   * Creates the default test content.
   */
  protected function createDefaultTestContent() {
    // Setup users.
    $this->user10 = $this->drupalCreateUser(['access content'], NULL, FALSE, $this->user10Data);
    $this->user20 = $this->drupalCreateUser(['access content'], NULL, FALSE, $this->user20Data);
    $this->user30 = $this->drupalCreateUser(['access content'], NULL, FALSE, $this->user30Data);

    $this->node1 = $this->drupalCreateNode($this->node1Data);
    $this->node2 = $this->drupalCreateNode($this->node2Data);
    $this->node3 = $this->drupalCreateNode($this->node3Data);

    $this->comment1 = Comment::create($this->comment1Data);
    $this->comment1->save();
    $this->comment2 = Comment::create($this->comment2Data);
    $this->comment2->save();

    $this->node2 = Node::load($this->node2->id());
    $this->node3 = Node::load($this->node3->id());
  }

  /**
   * Creates additional test content.
   */
  protected function createAdditionalTestContent() {
    $this->user30 = $this->drupalCreateUser(['access content'], NULL, FALSE, $this->user30Data);
    $this->comment3 = Comment::create($this->comment3Data);
    $this->comment3->save();

    $this->node2 = Node::load($this->node2->id());
  }

  /**
   * Deletes the test content.
   */
  protected function deleteTestContent() {
    $this->comment1->delete();
    $this->comment2->delete();
    if ($this->comment3) {
      $this->comment3->delete();
    }

    if ($this->node1) {
      $this->node1->delete();
    }
    $this->node2->delete();
    if ($this->node3) {
      $this->node3->delete();
    }

    $this->user10->delete();
    $this->user20->delete();
    if ($this->user30) {
      $this->user30->delete();
    }
  }

  /**
   * Verifies that the exported content was successfully imported.
   */
  public function assertTestContent() {
    $this->assertEquals(
      $this->comment1->toArray(),
      Comment::load(1)->toArray()
    );
    $this->assertEquals(
      $this->comment2->toArray(),
      Comment::load(2)->toArray()
    );
    if ($this->comment3) {
      $this->assertEquals(
        $this->comment3->toArray(),
        Comment::load(3)->toArray()
      );
    }

    $this->assertEquals(
      static::getComparableUserProperties($this->user10),
      User::load(10)->toArray()
    );
    $this->assertEquals(
      static::getComparableUserProperties($this->user20),
      User::load(20)->toArray()
    );
    if ($this->comment3) {
      $this->assertEquals(
        static::getComparableUserProperties($this->user30),
        User::load(30)->toArray()
      );
    }
    else {
      $this->assertEmpty(User::load(30));
    }

    $this->assertEquals($this->node2->toArray(), Node::load(2)->toArray());
  }

  /**
   * Verifies that the exported comment 1 json source is pretty printed.
   */
  public function assertComment1Json($comment_1_json_path) {
    $expected_file_content = <<<EOF
[
    {
        "cid": 1,
        "uuid": "{$this->comment1->uuid()}",
        "langcode": "en",
        "comment_type": "article",
        "status": "0",
        "uid": 20,
        "pid": null,
        "entity_id": 2,
        "subject": "Comment 1 subject",
        "name": null,
        "mail": null,
        "homepage": null,
        "hostname": null,
        "created": "{$this->comment1->getCreatedTime()}",
        "changed": "{$this->comment1->getChangedTime()}",
        "thread": "00\/",
        "entity_type": "node",
        "field_name": "comments",
        "default_langcode": "1",
        "comment_body": [
            {
                "value": "Comment 1 body",
                "format": "plain_text"
            }
        ]
    }
]
EOF;

    $this->assertEquals($expected_file_content, file_get_contents($comment_1_json_path));
  }

  /**
   * Removes the "existing" computed property from user pass.
   */
  protected static function getComparableUserProperties(UserInterface $user): array {
    // The "existing" property of the user pass shouldn't be compared.
    $user_array = $user->toArray();
    unset($user_array['pass'][0]['existing']);
    return $user_array;
  }

}
