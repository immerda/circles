<?php
/**
 * Circles - Bring cloud-users closer together.
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@pontapreta.net>
 * @copyright 2017
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Circles\Db;


use OC\L10N\L10N;
use OCA\Circles\Exceptions\CircleDoesNotExistException;
use OCA\Circles\Exceptions\FederatedLinkDoesNotExistException;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\FederatedLink;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\SharingFrame;
use OCA\Circles\Service\MiscService;
use OCP\IDBConnection;

class CirclesRequest extends CirclesRequestBuilder {


	/**
	 * forceGetCircle();
	 *
	 * returns data of a circle from its Id.
	 *
	 * WARNING: This function does not filters data regarding the current user/viewer.
	 *          In case of interaction with users, Please use getCircle() instead.
	 *
	 * @param int $circleId
	 *
	 * @return Circle
	 * @throws CircleDoesNotExistException
	 */
	public function forceGetCircle($circleId) {
		$qb = $this->getCirclesSelectSql();

		$this->limitToId($qb, $circleId);
		$cursor = $qb->execute();

		$data = $cursor->fetch();
		if ($data === false || $data === null) {
			throw new CircleDoesNotExistException($this->l10n->t('Circle not found'));
		}

		$entry = $this->parseCirclesSelectSql($data);

		return $entry;
	}


	public function getCircles($userId, $type = 0, $name = '', $level = 0) {
		if ($type === 0) {
			$type = Circle::CIRCLES_ALL;
		}

		$qb = $this->getCirclesSelectSql();
		$this->joinMembers($qb, 'c.id');

		$this->limitToUserId($qb, $userId);
		$this->limitToLevel($qb, $level, 'm', 'g');
		$this->limitRegardingCircleType($qb, $userId, -1, $type, $name);

		$this->leftJoinUserIdAsViewer($qb, $userId);
		$this->leftJoinOwner($qb);

		$result = [];
		$cursor = $qb->execute();

		while ($data = $cursor->fetch()) {
			if ($name === '' || stripos($data['name'], $name) !== false) {
				$result[] = $this->parseCirclesSelectSql($data);

//				$circle = Circle::fromArray($this->l10n, $data);
			//	$this->fillCircleUserIdAndOwner($circle, $data);
		//		$result[] = $circle;
			}
		}
		$cursor->closeCursor();

		return $result;
	}


	/**
	 *
	 * @param int $circleId
	 * @param string $userId
	 *
	 * @return Circle
	 * @throws CircleDoesNotExistException
	 */
	public function getCircle($circleId, $userId) {
		$qb = $this->getCirclesSelectSql();

		$this->limitToId($qb, $circleId);

		$this->leftJoinUserIdAsViewer($qb, $userId);
		$this->leftJoinOwner($qb);

		// If we need the viewer to be at least member of the circle
		//	$this->limitToLevel($qb, Member::LEVEL_MEMBER, 'u');
		$this->limitRegardingCircleType($qb, $userId, $circleId, Circle::CIRCLES_ALL, '');

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		if ($data === false || $data === null) {
			throw new CircleDoesNotExistException($this->l10n->t('Circle not found'));
		}

		$circle = $this->parseCirclesSelectSql($data);

		$circle->setGroupViewer(
			$this->membersRequest->forceGetHigherLevelGroupFromUser($circleId, $userId)
		);

		return $circle;
	}


	/**
	 * saveFrame()
	 *
	 * Insert a new entry in the database to save the SharingFrame.
	 *
	 * @param SharingFrame $frame
	 */
	public function saveFrame(SharingFrame $frame) {

		$qb = $this->getSharesInsertSql();
		$qb->setValue('circle_id', $qb->createNamedParameter($frame->getCircleId()))
		   ->setValue('source', $qb->createNamedParameter($frame->getSource()))
		   ->setValue('type', $qb->createNamedParameter($frame->getType()))
		   ->setValue('headers', $qb->createNamedParameter($frame->getHeaders(true)))
		   ->setValue('author', $qb->createNamedParameter($frame->getAuthor()))
		   ->setValue('cloud_id', $qb->createNamedParameter($frame->getCloudId()))
		   ->setValue('unique_id', $qb->createNamedParameter($frame->getUniqueId()))
		   ->setValue('payload', $qb->createNamedParameter($frame->getPayload(true)));

		$qb->execute();
	}


	public function updateFrame(SharingFrame $frame) {
		$qb = $this->getSharesUpdateSql($frame->getUniqueId());
		$qb->set('circle_id', $qb->createNamedParameter($frame->getCircleId()))
		   ->set('source', $qb->createNamedParameter($frame->getSource()))
		   ->set('type', $qb->createNamedParameter($frame->getType()))
		   ->set('headers', $qb->createNamedParameter($frame->getHeaders(true)))
		   ->set('author', $qb->createNamedParameter($frame->getAuthor()))
		   ->set('cloud_id', $qb->createNamedParameter($frame->getCloudId()))
		   ->set('unique_id', $qb->createNamedParameter($frame->getUniqueId()))
		   ->set('payload', $qb->createNamedParameter($frame->getPayload(true)));

		$qb->execute();
	}


	public function updateCircle(Circle $circle) {
		$qb = $this->getCirclesUpdateSql($circle->getId());
		$qb->set('name', $qb->createNamedParameter($circle->getName()))
		   ->set('description', $qb->createNamedParameter($circle->getDescription()))
		   ->set('settings', $qb->createNamedParameter($circle->getSettings(true)));

		$qb->execute();
	}


	/**
	 * @param string $uniqueId
	 *
	 * @return Circle
	 * @throws CircleDoesNotExistException
	 */
	public function getCircleFromUniqueId($uniqueId) {
		$qb = $this->getCirclesSelectSql();
		$this->limitToUniqueId($qb, (string)$uniqueId);

		$cursor = $qb->execute();
		$data = $cursor->fetch();

		if ($data === false) {
			throw new CircleDoesNotExistException(
				$this->l10n->t('Circle not found')
			);
		}
		$entry = $this->parseCirclesSelectSql($data);
		$cursor->closeCursor();

		return $entry;
	}


	/**
	 * @param int $circleId
	 * @param string $uniqueId
	 *
	 * @return SharingFrame
	 */
	public function getFrame($circleId, $uniqueId) {
		$qb = $this->getSharesSelectSql();
		$this->limitToUniqueId($qb, (string)$uniqueId);
		$this->limitToCircleId($qb, (int)$circleId);

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$entry = $this->parseSharesSelectSql($data);

		return $entry;
	}


	/**
	 * return the FederatedLink identified by a remote Circle UniqueId and the Token of the link
	 *
	 * @param string $token
	 * @param string $uniqueId
	 *
	 * @return FederatedLink
	 * @throws FederatedLinkDoesNotExistException
	 */
	public function getLinkFromToken($token, $uniqueId) {
		$qb = $this->getLinksSelectSql();
		$this->limitToUniqueId($qb, (string)$uniqueId);
		$this->limitToToken($qb, (string)$token);

		$cursor = $qb->execute();
		$data = $cursor->fetch();

		if ($data === false || $data === null) {
			throw new FederatedLinkDoesNotExistException(
				$this->l10n->t('Federated Link not found')
			);
		}

		$entry = $this->parseLinksSelectSql($data);
		$cursor->closeCursor();

		return $entry;
	}


	/**
	 * return the FederatedLink identified by a its Id
	 *
	 * @param int $linkId
	 *
	 * @return FederatedLink
	 * @throws FederatedLinkDoesNotExistException
	 */
	public function getLinkFromId($linkId) {
		$qb = $this->getLinksSelectSql();
		$this->limitToId($qb, (string)$linkId);

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false || $data === null) {
			throw new FederatedLinkDoesNotExistException(
				$this->l10n->t('Federated Link not found')
			);
		}

		$entry = $this->parseLinksSelectSql($data);

		return $entry;
	}


	/**
	 * returns all FederatedLink from a circle
	 *
	 * @param int $circleId
	 *
	 * @return FederatedLink[]
	 */
	public function getLinksFromCircle($circleId) {
		$qb = $this->getLinksSelectSql();
		$this->limitToCircleId($qb, $circleId);

		$links = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$link = $this->parseLinksSelectSql($data);
			if ($link !== null) {
				$links[] = $link;
			}
		}
		$cursor->closeCursor();

		return $links;
	}

	/**
	 * @param integer $circleId
	 * @param int $level
	 *
	 * @return Member[]
	 */
	public function getMembers($circleId, $level = Member::LEVEL_MEMBER) {
		$qb = $this->getMembersSelectSql();
		$this->limitToLevel($qb, $level);

		$this->joinCircles($qb, 'm.circle_id');
		$this->limitToCircleId($qb, $circleId);

		$qb->selectAlias('c.name', 'circle_name');

		$users = [];
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$member = $this->parseMembersSelectSql($data);
			if ($member !== null) {
				$users[] = $member;
			}
		}
		$cursor->closeCursor();

		return $users;
	}


}