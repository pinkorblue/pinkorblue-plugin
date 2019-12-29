<?php

namespace Robera\AB;

require_once('fixed-model.php');

class Variant extends FixedModel
{
    /**
     * Columns that can be edited - IE not primary key or timestamps if being used
     */
    public $table = AB_PLUGIN_PREFIX . '_variants';

    protected $fillable = [
        'post_id',
        'ab_test_id',
        'name',
        'kpi_value',
        'visits',
        'percentage'
    ];

    public function populate($post_id, $ab_test_id, $name, $kpi_value, $visits, $percentage)
    {
        $this->post_id = $post_id;
        $this->ab_test_id = $ab_test_id;
        $this->name = $name;
        $this->kpi_value = $kpi_value;
        $this->visits = $visits;
        $this->percentage = $percentage;
        $this->save();
    }

    /**
     * Disable created_at and update_at columns, unless you have those.
     */
    public $timestamps = true;

    /**
     * Set primary key as ID, because WordPress
     *
     * @var string
     */
    protected $primaryKey = 'ID';

    /**
     * Make ID guarded -- without this ID doesn't save.
     *
     * @var string
     */
    protected $guarded = [ 'ID' ];
}
