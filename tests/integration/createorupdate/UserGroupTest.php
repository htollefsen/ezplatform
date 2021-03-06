<?php

/**
 * This file is part of Transfer.
 *
 * For the full copyright and license information, please view the LICENSE file located
 * in the root directory.
 */

namespace Transfer\EzPlatform\tests\integration\createorupdate;

use eZ\Publish\API\Repository\Values\User\UserGroup;
use eZ\Publish\API\Repository\Values\User\UserGroupCreateStruct;
use Transfer\Adapter\Transaction\Request;
use Transfer\EzPlatform\Repository\Values\UserGroupObject;
use Transfer\EzPlatform\tests\testcase\UserGroupTestCase;

class UserGroupTest extends UserGroupTestCase
{
    public function setUp()
    {
        parent::setUp();
    }

    public function testCreateAndUpdateUsergroup()
    {
        $rootUsergroup = $this->getRootUserGroup();

        $countOriginal = count(static::$repository->getUserService()->loadSubUserGroups($rootUsergroup));

        $remote_id = 'my_user_group_10';
        $name = 'Test Usergroup';

        // Will find by remote_id
        $raw = $this->getUsergroup(
            array('name' => $name),
            null,
            $remote_id
        );

        $this->adapter->send(new Request(array(
            $raw,
        )));

        $userGroups = static::$repository->getUserService()->loadSubUserGroups($rootUsergroup);
        $this->assertCount(($countOriginal + 1), $userGroups);

        $real = null;
        foreach ($userGroups as $userGroup) {
            if ($userGroup->getField('name')->value->text == $name) {
                $real = $userGroup;
                $raw->data['id'] = $userGroup->id;
                $raw->data['remote_id'] = $userGroup->contentInfo->remoteId;
                break;
            }
        }

        $this->assertInstanceOf(UserGroup::class, $real);
        $this->assertEquals('Test Usergroup', $real->contentInfo->name);

        $raw->data['fields']['name'] = 'My Updated Testgroup';
        $raw->data['parent_id'] = $rootUsergroup->id;

        $this->adapter->send(new Request(array(
            $raw,
        )));

        $real = static::$repository->getUserService()->loadUserGroup($raw->data['id']);

        $this->assertInstanceOf(UserGroup::class, $real);
        $this->assertEquals('My Updated Testgroup', $real->getField('name')->value->text);

        // Checks that the usergroup has been moved.
        $this->assertEquals(12, $real->parentId);
    }

    public function testMoveUserGroup()
    {
        $remote_id = 'usergroup_moving';
        $users_members_node_id = 12;
        $users_administrators_node_id = 13;

        $userGroupObject = $this->getUsergroup(
            array('name' => 'This group is gonna move!'),
            $users_members_node_id,
            $remote_id
        );

        $response = $this->adapter->send(new Request(array(
            $userGroupObject,
        )));

        $userGroupObject = $response->getData();
        $userGroupObject = $userGroupObject[0];
        $this->assertEquals($users_members_node_id, $userGroupObject->data['parent_id']);

        $userGroupObject->data['parent_id'] = $users_administrators_node_id;
        $response = $this->adapter->send(new Request(array(
            $userGroupObject,
        )));
        $userGroupObject = $response->getData();
        $userGroupObject = $userGroupObject[0];
        $this->assertEquals($users_administrators_node_id, $userGroupObject->data['parent_id']);
    }

    /**
     * Tests usergroup struct callback.
     */
    public function testStructCallback()
    {
        $remote_id = 'integration_usergroup_struct_callback';
        $nodeId = $this->main_usergroup_id;

        $userGroupObject = $this->getUsergroup(
            array('name' => 'Struct callback usergroup'),
            $nodeId,
            $remote_id
        );

        $userGroupObject->setStructCallback(function (UserGroupCreateStruct $struct) {
            $struct->ownerId = 10;
        });

        $response = $this->adapter->send(new Request(array(
            $userGroupObject,
        )));

        /** @var UserGroupObject $userGroupObject */
        $userGroupObject = $response->getData();
        $userGroupObject = $userGroupObject[0];
        $userGroup = static::$repository->getUserService()->loadUserGroup($userGroupObject->getProperty('id'));

        $this->assertEquals(10, $userGroup->contentInfo->ownerId);
    }
}
