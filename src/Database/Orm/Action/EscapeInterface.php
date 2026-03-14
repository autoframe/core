<?php

namespace Autoframe\Core\Database\Orm\Action;

interface EscapeInterface
{
	/**
	 * Escape db name.
	 */
	public function escapeDbName(string $sDatabaseName): string;

	/**
	 * Escape table name.
	 */
	public function escapeTableName(string $sTableName): string;

	/**
	 * Escape column name.
	 */
	public function escapeColumnName(string $sColumnName): string;

	/**
	 * Escape value as mixed.
	 */
	public function escapeValueAsMixed($mValue);

	/**
	 * Escape value as string.
	 */
	public function escapeValueAsString($mValue): string;

}
