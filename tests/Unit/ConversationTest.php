<?php

namespace Musonza\Chat\Tests;

use Chat;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Musonza\Chat\ConfigurationManager;
use Musonza\Chat\Exceptions\DirectMessagingExistsException;
use Musonza\Chat\Exceptions\InvalidDirectMessageNumberOfParticipants;
use Musonza\Chat\Models\Conversation;
use Musonza\Chat\Tests\Helpers\Models\Client;

class ConversationTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function it_creates_a_conversation()
    {
        Chat::createConversation([$this->users[0], $this->users[1]]);

        $this->assertDatabaseHas($this->prefix.'conversations', ['id' => 1]);
    }

    /** @test */
    public function it_returns_a_conversation_given_the_id()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);

        $c = Chat::conversations()->getById($conversation->id);

        $this->assertEquals($conversation->id, $c->id);
    }

    /** @test */
    public function it_returns_participant_conversations()
    {
        Chat::createConversation([$this->users[0], $this->users[1]]);
        Chat::createConversation([$this->users[0], $this->users[2]]);

        $this->assertEquals(2, $this->users[0]->conversations()->count());
    }

    /** @test */
    public function it_can_mark_a_conversation_as_read()
    {
        $conversation = Chat::createConversation([
            $this->users[0],
            $this->users[1],
        ])->makeDirect();

        Chat::message('Hello there 0')->from($this->users[1])->to($conversation)->send();
        Chat::message('Hello there 0')->from($this->users[1])->to($conversation)->send();
        Chat::message('Hello there 0')->from($this->users[1])->to($conversation)->send();

        Chat::conversation($conversation)->setParticipant($this->users[0])->readAll();
        $this->assertEquals(0, $conversation->unReadNotifications($this->users[0])->count());
    }

    /** @test  */
    public function it_can_update_conversation_details()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);
        $data = ['title' => 'PHP Channel', 'description' => 'PHP Channel Description'];
        $conversation->update(['data' => $data]);

        $this->assertEquals('PHP Channel', $conversation->data['title']);
        $this->assertEquals('PHP Channel Description', $conversation->data['description']);
    }

    /** @test  */
    public function it_can_clear_a_conversation()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);

        Chat::message('Hello there 0')->from($this->users[0])->to($conversation)->send();
        Chat::message('Hello there 1')->from($this->users[0])->to($conversation)->send();
        Chat::message('Hello there 2')->from($this->users[0])->to($conversation)->send();

        Chat::conversation($conversation)->setParticipant($this->users[0])->clear();

        $messages = Chat::conversation($conversation)->setParticipant($this->users[0])->getMessages();

        $this->assertEquals($messages->count(), 0);
    }

    /** @test */
    public function it_can_create_a_conversation_between_two_users()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);

        $this->assertCount(2, $conversation->participants);
    }

    /** @test */
    public function it_can_remove_a_single_participant_from_conversation()
    {
        $clientModel = factory(Client::class)->create();
        $conversation = Chat::createConversation([$this->users[0], $this->users[1], $clientModel]);
        $conversation = Chat::conversation($conversation)->removeParticipants($this->users[0]);

        $this->assertEquals(2, $conversation->fresh()->participants()->count());

        $conversation = Chat::conversation($conversation)->removeParticipants($clientModel);
        $this->assertEquals(1, $conversation->fresh()->participants()->count());
    }

    /** @test */
    public function it_can_remove_multiple_users_from_conversation()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);

        $conversation = Chat::conversation($conversation)->removeParticipants([$this->users[0], $this->users[1]]);

        $this->assertEquals(0, $conversation->fresh()->participants->count());
    }

    /** @test */
    public function it_can_add_a_single_user_to_conversation()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);

        $this->assertEquals($conversation->participants->count(), 2);

        $userThree = $this->createUsers(1);

        Chat::conversation($conversation)->addParticipants([$userThree[0]]);

        $this->assertEquals($conversation->fresh()->participants->count(), 3);
    }

    /** @test */
    public function it_can_add_multiple_users_to_conversation()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);

        $this->assertEquals($conversation->participants->count(), 2);

        $otherUsers = $this->createUsers(5);

        Chat::conversation($conversation)->addParticipants($otherUsers->all());

        $this->assertEquals($conversation->fresh()->participants->count(), 7);
    }

    /** @test */
    public function it_can_return_conversation_recent_messsage()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);
        Chat::message('Hello 1')->from($this->users[1])->to($conversation)->send();
        Chat::message('Hello 2')->from($this->users[0])->to($conversation)->send();

        $conversation2 = Chat::createConversation([$this->users[0], $this->users[2]]);
        Chat::message('Hello Man 4')->from($this->users[0])->to($conversation2)->send();

        $conversation3 = Chat::createConversation([$this->users[0], $this->users[3]]);
        Chat::message('Hello Man 5')->from($this->users[3])->to($conversation3)->send();
        Chat::message('Hello Man 6')->from($this->users[0])->to($conversation3)->send();
        Chat::message('Hello Man 3')->from($this->users[2])->to($conversation2)->send();

        $message7 = Chat::message('Hello Man 10')->from($this->users[0])->to($conversation2)->send();

        $this->assertEquals($message7->id, $conversation2->last_message->id);
    }

    /** @test */
    public function it_returns_last_message_as_null_when_the_very_last_message_was_deleted()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);
        $message = Chat::message('Hello & Bye')->from($this->users[0])->to($conversation)->send();
        Chat::message($message)->setParticipant($this->users[0])->delete();

        $conversations = Chat::conversations()->setParticipant($this->users[0])->get();

        $this->assertNull($conversations->get(0)->last_message);
    }

    /** @test */
    public function it_returns_correct_attributes_in_last_message()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);
        Chat::message('Hello')->from($this->users[0])->to($conversation)->send();

        $conversations = Chat::conversations()->setParticipant($this->users[0])->get();

        $this->assertTrue((bool) $conversations->get(0)->last_message->is_seen);

        $conversations = Chat::conversations()->setParticipant($this->users[1])->get();

        $this->assertFalse((bool) $conversations->get(0)->last_message->is_seen);
    }

    /** @test */
    public function it_returns_the_correct_order_of_conversations_when_updated_at_is_duplicated()
    {
        $auth = $this->users[0];

        $conversation = Chat::createConversation([$auth, $this->users[1]]);
        Chat::message('Hello-'.$conversation->id)->from($auth)->to($conversation)->send();

        $conversation = Chat::createConversation([$auth, $this->users[2]]);
        Chat::message('Hello-'.$conversation->id)->from($auth)->to($conversation)->send();

        $conversation = Chat::createConversation([$auth, $this->users[3]]);
        Chat::message('Hello-'.$conversation->id)->from($auth)->to($conversation)->send();

        $conversations = Chat::conversations()->setPaginationParams(['sorting' => 'desc'])->setParticipant($auth)->limit(1)->page(1)->get();
        $this->assertEquals('Hello-3', $conversations->items()[0]->last_message->body);

        $conversations = Chat::conversations()->setPaginationParams(['sorting' => 'desc'])->setParticipant($auth)->limit(1)->page(2)->get();
        $this->assertEquals('Hello-2', $conversations->items()[0]->last_message->body);

        $conversations = Chat::conversations()->setPaginationParams(['sorting' => 'desc'])->setParticipant($auth)->limit(1)->page(3)->get();
        $this->assertEquals('Hello-1', $conversations->items()[0]->last_message->body);
    }

    /** @test */
    public function it_allows_setting_private_or_public_conversation()
    {
        $conversation = Chat::createConversation([
            $this->users[0],
            $this->users[1],
        ])
            ->makePrivate();

        $this->assertTrue($conversation->private);

        $conversation->makePrivate(false);

        $this->assertFalse($conversation->private);
    }

    /**
     * DIRECT MESSAGING.
     *
     * @test
     */
    public function it_creates_direct_messaging()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]])
            ->makeDirect();

        $this->assertTrue($conversation->direct_message);
    }

    /** @test */
    public function it_does_not_duplicate_direct_messaging()
    {
        Chat::createConversation([$this->users[0], $this->users[1]])
            ->makeDirect();

        $this->expectException(DirectMessagingExistsException::class);

        Chat::createConversation([$this->users[0], $this->users[1]])
            ->makeDirect();
    }

    /** @test */
    public function it_prevents_additional_participants_to_direct_conversation()
    {
        /** @var Conversation $conversation */
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]])
            ->makeDirect();

        $this->expectException(InvalidDirectMessageNumberOfParticipants::class);
        $conversation->addParticipants([$this->users[2]]);
    }

    /** @test */
    public function it_can_return_a_conversation_between_users()
    {
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]])->makeDirect();
        $conversation2 = Chat::createConversation([$this->users[0], $this->users[2]]);
        $conversation3 = Chat::createConversation([$this->users[0], $this->users[3]])->makeDirect();

        $c1 = Chat::conversations()->between($this->users[0], $this->users[1]);
        $this->assertEquals($conversation->id, $c1->id);

        $c3 = Chat::conversations()->between($this->users[0], $this->users[3]);
        $this->assertEquals($conversation3->id, $c3->id);
    }

    /** @test */
    public function it_filters_conversations_by_type()
    {
        Chat::createConversation([$this->users[0], $this->users[1]])->makePrivate();
        Chat::createConversation([$this->users[0], $this->users[1]])->makePrivate(false);
        Chat::createConversation([$this->users[0], $this->users[1]])->makePrivate();
        Chat::createConversation([$this->users[0], $this->users[2]])->makeDirect();

        $allConversations = Chat::conversations()->setParticipant($this->users[0])->get();
        $this->assertCount(4, $allConversations, 'All Conversations');

        $privateConversations = Chat::conversations()->setParticipant($this->users[0])->isPrivate()->get();
        $this->assertCount(3, $privateConversations, 'Private Conversations');

        $publicConversations = Chat::conversations()->setParticipant($this->users[0])->isPrivate(false)->get();
        $this->assertCount(1, $publicConversations, 'Public Conversations');

        $directConversations = Chat::conversations()->setParticipant($this->users[0])->isDirect()->get();
        $this->assertCount(1, $directConversations, 'Direct Conversations');
    }

    /**
     * Conversation Settings.
     *
     * @test
     */
    public function it_can_update_participant_conversation_settings()
    {
        /** @var Conversation $conversation */
        $conversation = Chat::createConversation([$this->users[0], $this->users[1]]);

        $settings = ['mute_mentions' => true];

        Chat::conversation($conversation)
            ->setParticipant($this->users[0])
            ->updateSettings($settings);

        $this->assertDatabaseHas(
            ConfigurationManager::PARTICIPATION_TABLE,
            [
                'messageable_type' => get_class($this->users[0]),
                'messageable_id'   => $this->users[0]->getKey(),
                'settings'         => json_encode($settings),
            ]
        );
    }
}
