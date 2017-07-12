<?php
/**
 * Created by PhpStorm.
 * User: maxence
 * Date: 7/4/17
 * Time: 5:01 PM
 */

namespace OCA\Circles\Db;


use OCP\DB\QueryBuilder\IQueryBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use OC\L10N\L10N;
use OCA\Circles\Service\MiscService;
use OCP\IDBConnection;

class CoreRequestBuilder {

	const TABLE_CIRCLES = 'circles_circles';
	const TABLE_MEMBERS = 'circles_members';
	const TABLE_GROUPS = 'circles_groups';

	const NC_TABLE_GROUP_USER = 'group_user';

	/** @var IDBConnection */
	protected $dbConnection;

	/** @var L10N */
	protected $l10n;

	/** @var MiscService */
	protected $miscService;

	/** @var string */
	protected $default_select_alias;


	/**
	 * RequestBuilder constructor.
	 *
	 * @param L10N $l10n
	 * @param IDBConnection $connection
	 * @param MiscService $miscService
	 */
	public function __construct(L10N $l10n, IDBConnection $connection, MiscService $miscService) {
		$this->l10n = $l10n;
		$this->dbConnection = $connection;
		$this->miscService = $miscService;
	}


	/**
	 * Limit the request by its Id.
	 *
	 * @param IQueryBuilder $qb
	 * @param int $id
	 */
	protected function limitToId(IQueryBuilder &$qb, $id) {
		$this->limitToDBField($qb, 'id', $id);
	}


	/**
	 * Limit the request by its UniqueId.
	 *
	 * @param IQueryBuilder $qb
	 * @param int $uniqueId
	 */
	protected function limitToUniqueId(IQueryBuilder &$qb, $uniqueId) {
		$this->limitToDBField($qb, 'unique_id', $uniqueId);
	}


	/**
	 * Limit the request by its Token.
	 *
	 * @param IQueryBuilder $qb
	 * @param string $token
	 */
	protected function limitToToken(IQueryBuilder &$qb, $token) {
		$this->limitToDBField($qb, 'token', $token);
	}


	/**
	 * Limit the request to the Circle by its Id.
	 *
	 * @param IQueryBuilder $qb
	 * @param int $circleId
	 */
	protected function limitToCircleId(IQueryBuilder &$qb, $circleId) {
		$this->limitToDBField($qb, 'circle_id', $circleId);
	}


	/**
	 * Limit the request to the Group by its Id.
	 *
	 * @param IQueryBuilder $qb
	 * @param int $groupId
	 */
	protected function limitToGroupId(IQueryBuilder &$qb, $groupId) {
		$this->limitToDBField($qb, 'group_id', $groupId);
	}


	/**
	 * Limit the request to a minimum member level.
	 *
	 * @param IQueryBuilder $qb
	 * @param integer $level
	 * @param string $pf
	 * @param string $pf2
	 */
	protected function limitToLevel(IQueryBuilder &$qb, $level, $pf = '', $pf2 = '') {
		$expr = $qb->expr();
		if ($pf === '') {
			$pf =
				($qb->getType() === QueryBuilder::SELECT) ? $this->default_select_alias . '.' : '';
		} else {
			$pf .= '.';
		}

		if ($pf2 === '') {
			$qb->andWhere($expr->gte($pf . 'level', $qb->createNamedParameter($level)));
		} else {
			$pf2 .= '.';
			$qb->andWhere(
				$expr->orX(
					$expr->gte($pf . 'level', $qb->createNamedParameter($level)),
					$expr->gte($pf2 . 'level', $qb->createNamedParameter($level))
				)
			);
		}
	}


	/**
	 * @param IQueryBuilder $qb
	 * @param string $field
	 * @param string|integer $value
	 */
	private function limitToDBField(IQueryBuilder & $qb, $field, $value) {
		$expr = $qb->expr();
		$pf = ($qb->getType() === QueryBuilder::SELECT) ? $this->default_select_alias . '.' : '';
		$qb->andWhere($expr->eq($pf . $field, $qb->createNamedParameter($value)));
	}


	/**
	 * link to the groupId/UserId of the NC DB.
	 *
	 * @param IQueryBuilder $qb
	 * @param string $userId
	 */
	protected function limitToNCGroupUser(IQueryBuilder $qb, $userId) {
		$expr = $qb->expr();
		$pf = ($qb->getType() === QueryBuilder::SELECT) ? $this->default_select_alias . '.' : '';

		$qb->from(self::NC_TABLE_GROUP_USER, 'ncgu');
		$qb->andWhere(
			$expr->andX(
				$expr->eq($pf . 'group_id', 'ncgu.gid'),
				$expr->eq(
					'ncgu.uid',
					$qb->createNamedParameter($userId)
				)
			)
		);
	}


	/**
	 * link to the groupId/UserId of the NC DB.
	 *
	 * @param IQueryBuilder $qb
	 * @param $fieldGroup
	 * @param $fieldUser
	 */
	protected function joinNCGroupAndUser(IQueryBuilder $qb, $fieldGroup, $fieldUser) {
		$expr = $qb->expr();

		$qb->from(self::NC_TABLE_GROUP_USER, 'ncgu');
		$qb->andWhere(
			$expr->andX(
				$expr->eq('ncgu.gid', $fieldGroup),
				$expr->eq('ncgu.uid', $fieldUser)
			)
		);
	}
}