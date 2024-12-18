<?php

namespace Franzose\ClosureTable\Models;

use Franzose\ClosureTable\Contracts\ClosureTableInterface;
use Illuminate\Database\Eloquent\Model as Eloquent;

/**
 * Basic ClosureTable model. Performs actions on the relationships table.
 *
 * @property mixed ancestor Alias for the ancestor attribute name
 * @property mixed descendant Alias for the descendant attribute name
 * @property int depth Alias for the depth attribute name
 */
class ClosureTable extends Eloquent implements ClosureTableInterface
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'entities_closure';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'closure_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Inserts new node into closure table.
     *
     * @param  mixed  $ancestorId
     * @param  mixed  $descendantId
     */
    public function insertNode($ancestorId, $descendantId): void
    {
        $rows = $this->selectRowsToInsert($ancestorId, $descendantId);

        $columns = [
            $this->getAncestorColumn(),
            $this->getDescendantColumn(),
            $this->getDepthColumn(),
        ];
        $this->insertUsing($columns, $rows);
    }

    private function selectRowsToInsert($ancestorId, $descendantId)
    {
        $table = $this->getPrefixedTable();
        $ancestor = $this->getAncestorColumn();
        $descendant = $this->getDescendantColumn();
        $depth = $this->getDepthColumn();

        $teste = $this->selectRaw("tbl.$ancestor AS $ancestor, ?::bigint AS $descendant, tbl.$depth+1 AS $depth", [$descendantId])
            ->from($table, 'tbl')
            ->whereRaw("tbl.$descendant = ?", [$ancestorId])
            ->unionAll(function ($query) use ($ancestor, $descendant, $depth, $descendantId) {
                $query->selectRaw("?::bigint AS $ancestor, ?::bigint AS $descendant, 0 AS $depth", [
                    $descendantId,
                    $descendantId,
                ]);
            });

        return $teste;
    }

    /**
     * Make a node a descendant of another ancestor or makes it a root node.
     *
     * @param  mixed  $ancestorId
     *
     * @return void
     */
    public function moveNodeTo($ancestorId = null)
    {
        $table = $this->getPrefixedTable();
        $ancestor = $this->getAncestorColumn();
        $descendant = $this->getDescendantColumn();
        $depth = $this->getDepthColumn();

        // Prevent constraint collision
        if ($ancestorId !== null && $this->ancestor === $ancestorId) {
            return;
        }

        $this->unbindRelationships();

        // Since we have already unbound the node relationships,
        // given null ancestor id, we have nothing else to do,
        // because now the node is already root.
        if ($ancestorId === null) {
            return;
        }

        $supertbl = $this->newQuery()
            ->selectRaw("supertbl.{$ancestor}, subtbl.{$descendant}, supertbl.{$depth} + subtbl.{$depth} + 1")
            ->from("{$table} as supertbl")
            ->crossJoin("{$table} as subtbl")
            ->where("supertbl.{$descendant}", $ancestorId)
            ->where("subtbl.{$ancestor}", $this->descendant);

        $this->insertUsing([$ancestor, $descendant, $depth], $supertbl);
    }

    /**
     * Unbinds current relationships.
     *
     * @return void
     */
    protected function unbindRelationships()
    {
        $table = $this->getPrefixedTable();
        $ancestorColumn = $this->getAncestorColumn();
        $descendantColumn = $this->getDescendantColumn();

        $descendants = $this->select($descendantColumn)
            ->from($table)
            ->where($ancestorColumn, $this->descendant);

        $ancestors = $this->select($ancestorColumn)
            ->from($table)
            ->where($descendantColumn, $this->descendant)
            ->where($ancestorColumn, '<>', $this->descendant);

        $this->whereIn($descendantColumn, $descendants)
            ->whereIn($ancestorColumn, $ancestors)
            ->delete();
    }

    /**
     * Get table name with custom prefix for use in raw queries.
     *
     * @return string
     */
    public function getPrefixedTable()
    {
        return $this->getConnection()->getTablePrefix() . $this->getTable();
    }

    /**
     * Get value of the "ancestor" attribute.
     *
     * @return int
     */
    public function getAncestorAttribute()
    {
        return $this->getAttributeFromArray($this->getAncestorColumn());
    }

    /**
     * Set new ancestor id.
     *
     * @param  mixed  $value
     */
    public function setAncestorAttribute($value)
    {
        $this->attributes[$this->getAncestorColumn()] = $value;
    }

    /**
     * Get the fully qualified "ancestor" column.
     *
     * @return string
     */
    public function getQualifiedAncestorColumn()
    {
        return $this->getTable() . '.' . $this->getAncestorColumn();
    }

    /**
     * Get the short name of the "ancestor" column.
     *
     * @return string
     */
    public function getAncestorColumn()
    {
        return 'ancestor';
    }

    /**
     * Get value of the "descendant" attribute.
     *
     * @return int
     */
    public function getDescendantAttribute()
    {
        return $this->getAttributeFromArray($this->getDescendantColumn());
    }

    /**
     * Set new descendant id.
     *
     * @param  mixed  $value
     */
    public function setDescendantAttribute($value)
    {
        $this->attributes[$this->getDescendantColumn()] = $value;
    }

    /**
     * Get the fully qualified "descendant" column.
     *
     * @return string
     */
    public function getQualifiedDescendantColumn()
    {
        return $this->getTable() . '.' . $this->getDescendantColumn();
    }

    /**
     * Get the short name of the "descendant" column.
     *
     * @return string
     */
    public function getDescendantColumn()
    {
        return 'descendant';
    }

    /**
     * Gets value of the "depth" attribute.
     *
     * @return int
     */
    public function getDepthAttribute()
    {
        return $this->getAttributeFromArray($this->getDepthColumn());
    }

    /**
     * Sets new depth.
     *
     * @param  mixed  $value
     */
    public function setDepthAttribute($value)
    {
        $this->attributes[$this->getDepthColumn()] = (int) $value;
    }

    /**
     * Gets the fully qualified "deleted at" column.
     *
     * @return string
     */
    public function getQualifiedDepthColumn()
    {
        return $this->getTable() . '.' . $this->getDepthColumn();
    }

    /**
     * Get the short name of the "depth" column.
     *
     * @return string
     */
    public function getDepthColumn()
    {
        return 'depth';
    }
}
