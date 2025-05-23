<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Comments\Tests\Unit\Activity;

use OCA\Comments\Activity\Listener;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\App\IAppManager;
use OCP\Comments\CommentsEvent;
use OCP\Comments\IComment;
use OCP\Files\Config\ICachedMountFileInfo;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\IShareHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ListenerTest extends TestCase {
	protected IManager&MockObject $activityManager;
	protected IUserSession&MockObject $session;
	protected IAppManager&MockObject $appManager;
	protected IMountProviderCollection&MockObject $mountProviderCollection;
	protected IRootFolder&MockObject $rootFolder;
	protected IShareHelper&MockObject $shareHelper;
	protected Listener $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->activityManager = $this->createMock(IManager::class);
		$this->session = $this->createMock(IUserSession::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->mountProviderCollection = $this->createMock(IMountProviderCollection::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->shareHelper = $this->createMock(IShareHelper::class);

		$this->listener = new Listener(
			$this->activityManager,
			$this->session,
			$this->appManager,
			$this->mountProviderCollection,
			$this->rootFolder,
			$this->shareHelper
		);
	}

	public function testCommentEvent(): void {
		$this->appManager->expects($this->any())
			->method('isEnabledForAnyone')
			->with('activity')
			->willReturn(true);

		$comment = $this->createMock(IComment::class);
		$comment->expects($this->any())
			->method('getObjectType')
			->willReturn('files');

		/** @var CommentsEvent|MockObject $event */
		$event = $this->createMock(CommentsEvent::class);
		$event->expects($this->any())
			->method('getComment')
			->willReturn($comment);
		$event->expects($this->any())
			->method('getEvent')
			->willReturn(CommentsEvent::EVENT_ADD);

		/** @var IUser|MockObject $ownerUser */
		$ownerUser = $this->createMock(IUser::class);
		$ownerUser->expects($this->any())
			->method('getUID')
			->willReturn('937393');

		/** @var MockObject $mount */
		$mount = $this->createMock(ICachedMountFileInfo::class);
		$mount->expects($this->any())
			->method('getUser')
			->willReturn($ownerUser); // perhaps not the right user, but does not matter in this scenario

		$mounts = [ $mount, $mount ]; // to make sure duplicates are dealt with

		$userMountCache = $this->createMock(IUserMountCache::class);
		$userMountCache->expects($this->any())
			->method('getMountsForFileId')
			->willReturn($mounts);

		$this->mountProviderCollection->expects($this->any())
			->method('getMountCache')
			->willReturn($userMountCache);

		$node = $this->createMock(Node::class);
		$nodes = [ $node ];

		$ownerFolder = $this->createMock(Folder::class);
		$ownerFolder->expects($this->any())
			->method('getById')
			->willReturn($nodes);

		$this->rootFolder->expects($this->any())
			->method('getUserFolder')
			->willReturn($ownerFolder);

		$al = [ 'users' => [
			'873304' => 'i/got/it/here',
			'254342' => 'there/i/have/it',
			'sandra' => 'and/here/i/placed/it'
		]];
		$this->shareHelper->expects($this->any())
			->method('getPathsForAccessList')
			->willReturn($al);

		$this->session->expects($this->any())
			->method('getUser')
			->willReturn($ownerUser);

		/** @var MockObject $activity */
		$activity = $this->createMock(IEvent::class);
		$activity->expects($this->exactly(count($al['users'])))
			->method('setAffectedUser');
		$activity->expects($this->once())
			->method('setApp')
			->with('comments')
			->willReturnSelf();
		$activity->expects($this->once())
			->method('setType')
			->with('comments')
			->willReturnSelf();
		$activity->expects($this->once())
			->method('setAuthor')
			->with($ownerUser->getUID())
			->willReturnSelf();
		$activity->expects($this->once())
			->method('setObject')
			->with('files', $this->anything())
			->willReturnSelf();
		$activity->expects($this->once())
			->method('setMessage')
			->with('add_comment_message', $this->anything())
			->willReturnSelf();

		$this->activityManager->expects($this->once())
			->method('generateEvent')
			->willReturn($activity);
		$this->activityManager->expects($this->exactly(count($al['users'])))
			->method('publish');

		$this->listener->commentEvent($event);
	}
}
