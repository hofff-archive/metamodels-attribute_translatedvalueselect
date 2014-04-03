<?php

namespace MetaModels\Attribute\TranslatedValueSelect;

use MetaModels\Attribute\TranslatedReference;

/**
 * @author Oliver Hoff <oliver@hofff.com>
 */
class TranslatedValueSelect extends TranslatedReference {

	/**
	 * @param interger|array<integer> $ids
	 * @param null|string|array<string> $columns
	 * @return array<integer, array>
	 */
	public function getReferencedData($ids, $columns = null) {
		$ids = (array) $ids;
		if(!$ids) {
			return array();
		}

		$selectTable = $this->get('select_table');
		$idColumn = $this->get('select_id');
		$columns = $columns === null ? '*' : implode(',', array_unique(array_merge(array($idColumn), (array) $columns)));
		$idWildcards = self::generateWildcards($ids);
		$condition = html_entity_decode($this->get('select_where'));
		strlen($condition) && $condition = 'AND ' . $condition;

		$sql = <<<SQL
SELECT	$columns
FROM	$selectTable
WHERE	$idColumn IN ($idWildcards)
$condition
SQL;
		$result = \Database::getInstance()->prepare($sql)->executeUncached($ids);

		while($result->next()) {
			$data[$result->$idColumn] = $result->row();
		}

		return (array) $data;
	}

	/* (non-PHPdoc)
	 * @see \MetaModels\Attribute\Base::getFilterUrlValue()
	 */
	public function getFilterUrlValue($value) {
		$aliasColumn = $this->get('select_alias') ?: $this->get('select_id');
		$data = $this->getReferencedData($value, $aliasColumn);
		return urlencode($data[$value] ? $data[$value][$aliasColumn] : $value);
	}

	/* (non-PHPdoc)
	 * @see \MetaModels\Attribute\Base::getAttributeSettingNames()
	 */
	public function getAttributeSettingNames() {
		return array_merge(parent::getAttributeSettingNames(), array(
			'select_table',
			'select_column',
			'select_id',
			'select_alias',
			'select_where',
			'select_sorting',
			'select_as_radio',
			'includeBlankOption',
			'mandatory',
			'chosen',
			'filterable',
			'searchable',
			'sortable',
			'flag'
		));
	}

	/* (non-PHPdoc)
	 * @see \MetaModels\Attribute\TranslatedReference::getValueTable()
	*/
	protected function getValueTable() {
		return 'tl_metamodel_translatedvalueselect';
	}

	/* (non-PHPdoc)
	 * @see \MetaModels\Attribute\TranslatedReference::valueToWidget()
	*/
	public function valueToWidget($value) {
		return $value;
	}

	/* (non-PHPdoc)
	 * @see \MetaModels\Attribute\TranslatedReference::widgetToValue()
	*/
	public function widgetToValue($value, $id) {
		return $value;
	}

	/* (non-PHPdoc)
	 * @see \MetaModels\Attribute\Base::getFieldDefinition()
	 */
	public function getFieldDefinition($overrides = array()) {
		$field = parent::getFieldDefinition($overrides);
		$field['inputType'] = $overrides['select_as_radio'] ? 'radio' : 'select';
		$field['options'] = $this->getFilterOptions(null, false);
		return $field;
	}

	/* (non-PHPdoc)
	 * @see \MetaModels\Attribute\TranslatedReference::getFilterOptions()
	 */
	public function getFilterOptions($ids, $usedOnly, &$count = null) {
		if($ids !== null && !$ids) {
			return array();
		}

		$joinTable = $this->getValueTable();
		$selectTable = $this->get('select_table');
		$idColumn = $this->get('select_id');
		$valueColumn = $this->get('select_column');
		$sortColumn = $this->get('select_sorting') ?: $valueColumn;
		$aliasColumn = $this->get('select_alias') ?: $idColumn;

		$where = html_entity_decode($this->get('select_where'));
		strlen($where) && $where = 'WHERE ' . $where;

		if($ids) {
			$ids = array_values($ids);
			$idWildcards = self::generateWildcards($ids);
			$idCondition = 'AND _join.item_id IN (' . $idWildcards . ')';
		}
		if($ids || $usedOnly) {
			$having = 'HAVING _count > 0';
		}

		$sql = <<<SQL
SELECT			COALESCE(COUNT(DISTINCT _active.item_id), 0) +
				COALESCE(COUNT(DISTINCT _fallback.item_id), 0) AS _count,
				_select.*

FROM			$selectTable	AS _select

LEFT JOIN		$joinTable		AS _active		ON _active.value = _select.$idColumn
												$idCondition
												AND _active.att_id = ?
												AND _active.language = ?

LEFT JOIN		$joinTable		AS _fallback	ON _fallback.item_id != _active.item_id
												AND _fallback.value = _select.$idColumn
												$idCondition
												AND _fallback.att_id = ?
												AND _fallback.language = ?
$where
GROUP BY		_select.$idColumn
$having
ORDER BY		_select.$sortColumn
SQL;

		$params = (array) $ids;
		$params[] = $this->get('id');
		$params[] = $this->getMetaModel()->getActiveLanguage();
		$params = array_merge($params, (array) $ids);
		$params[] = $this->get('id');
		$params[] = $this->getMetaModel()->getFallbackLanguage();

		$result = \Database::getInstance()->prepare($sql)->execute($params);

		$count = array();
		while($result->next()) {
			$options[$result->$aliasColumn] = $result->$valueColumn;
			$count[$result->$aliasColumn] = $result->_count;
		}

		return (array) $options;
	}

	/* (non-PHPdoc)
	 * @see \MetaModels\Attribute\TranslatedReference::searchForInLanguages()
	 */
	public function searchForInLanguages($pattern, $languages = array()) {
		$joinTable = $this->getValueTable();
		$selectTable = $this->get('select_table');
		$idColumn = $this->get('select_id');
		$valueColumn = $this->get('select_column');

		$languages = (array) $languages;
		if($languages) {
			$languageWildcards = self::generateWildcards($languages);
			$languageCondition = 'AND _join.language IN (' . $languageWildcards . ')';
		}

		$sql = <<<SQL
SELECT	DISTINCT _join.item_id AS id
FROM	$joinTable		AS _join
JOIN	$selectTable	AS _select	ON _select.$idColumn = _join.value
WHERE	_select.$valueColumn LIKE ?
AND		_join.att_id = ?
$languageCondition
SQL;

		$params[] = str_replace(array('*', '?'), array('%', '_'), $pattern);
		$params[] = $this->get('id');
		$params = array_merge($params, $languages);

		$result = \Database::getInstance()->prepare($sql)->executeUncached($params);

		return $result->fetchEach('id');
	}

	/* (non-PHPdoc)
	 * @see \MetaModels\Attribute\TranslatedReference::sortIds()
	 */
	public function sortIds($ids, $direction) {
		$ids = (array) $ids;
		if(count($ids) < 2) {
			return $ids;
		}

		$modelTable = $this->getMetaModel()->getTableName();
		$joinTable = $this->getValueTable();
		$selectTable = $this->get('select_table');
		$idColumn = $this->get('select_id');
		$valueColumn = $this->get('select_column');
		$sortColumn = $this->get('select_sorting') ?: $valueColumn;
		$direction == 'DESC' || $direction = 'ASC';

		$idWildcards = self::generateWildcards($ids);
		$sql = <<<SQL
SELECT		_model.id
FROM		$modelTable		AS _model

LEFT JOIN	$joinTable		AS _active		ON _active.item_id = _model.id
											AND _active.att_id = ?
											AND _active.language = ?

LEFT JOIN	$joinTable		AS _fallback	ON _active.item_id IS NULL
											AND _fallback.item_id = _model.id
											AND _fallback.att_id = ?
											AND _fallback.language = ?

LEFT JOIN	$selectTable	AS _select		ON _select.$idColumn = _active.value
											OR _select.$idColumn = _fallback.value

WHERE		_model.id IN ($idWildcards)

ORDER BY	_select.$sortColumn $direction
SQL;

		$params[] = $this->get('id');
		$params[] = $this->getMetaModel()->getActiveLanguage();
		$params[] = $this->get('id');
		$params[] = $this->getMetaModel()->getFallbackLanguage();
		$params = array_merge($params, $ids);

		$result = \Database::getInstance()->prepare($sql)->execute($params);

		return $result->fetchEach('id');
	}

	/* (non-PHPdoc)
	 * @see \MetaModels\Attribute\TranslatedReference::setTranslatedDataFor()
	 */
	public function setTranslatedDataFor($values, $language) {
		$values = (array) $values;
		if(!$values) {
			return;
		}

		$this->unsetValueFor(array_keys($values), $language);

		$wildcards = self::generateWildcards($values, '(?,?,?,?,?)');
		$joinTable = $this->getValueTable();
		$time = time();

		$sql = <<<SQL
INSERT INTO	$joinTable
			(att_id, item_id, language, tstamp, value)
VALUES		$wildcards
SQL;

		foreach($values as $id => $value) {
			$params[] = $this->get('id');
			$params[] = $id;
			$params[] = $language;
			$params[] = $time;
			$params[] = $value;
		}

		\Database::getInstance()->prepare($sql)->executeUncached($params);
	}

	/* (non-PHPdoc)
	 * @see \MetaModels\Attribute\TranslatedReference::getTranslatedDataFor()
	 */
	public function getTranslatedDataFor($ids, $language) {
		$ids = (array) $ids;
		if(!$ids) {
			return array();
		}

		$idWildcards = self::generateWildcards($ids);
		$joinTable = $this->getValueTable();

		$sql = <<<SQL
SELECT		item_id AS id, value
FROM		$joinTable
WHERE		att_id = ?
AND			language = ?
AND			item_id IN ($idWildcards)
SQL;

		$params[] = $this->get('id');
		$params[] = $language;
		$params = array_merge($params, $ids);

		$result = \Database::getInstance()->prepare($sql)->executeUncached($params);
		while($result->next()) {
			$values[$result->id] = $result->value;
		}

		return (array) $values;
	}

	/* (non-PHPdoc)
	 * @see \MetaModels\Attribute\TranslatedReference::unsetValueFor()
	 */
	public function unsetValueFor($ids, $language) {
		$ids = (array) $ids;
		if(!$ids) {
			return;
		}

		$idWildcards = self::generateWildcards($ids);
		$joinTable = $this->getValueTable();

		$sql = <<<SQL
DELETE FROM	$joinTable
WHERE		att_id = ?
AND			language = ?
AND			item_id IN ($idWildcards)
SQL;

		$params[] = $this->get('id');
		$params[] = $language;
		$params = array_merge($params, $ids);

		\Database::getInstance()->prepare($sql)->executeUncached($params);
	}

	/**
	 * @param array $values
	 * @param string $wildcard
	 * @return string
	 */
	public static function generateWildcards(array $values, $wildcard = '?') {
		return rtrim(str_repeat($wildcard . ',', count($values)), ',');
	}

}
