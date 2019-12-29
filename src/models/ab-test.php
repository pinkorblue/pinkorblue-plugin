<?php

namespace Robera\AB;

require_once('fixed-model.php');

class ABTest extends FixedModel
{
    /**
     * Columns that can be edited - IE not primary key or timestamps if being used
     */
    public $table = AB_PLUGIN_PREFIX . '_ab_tests';

    protected $fillable = [
        'post_id',
        'description',
        'name',
        'state',
        'kpi',
        'target_page_id',
        'type'
    ];

    public function populate($post_id, $name, $description, $kpi, $target_page_id, $type, $state = AB_TEST_STATE_NOT_STARTED)
    {
        $this->post_id = $post_id;
        $this->name = $name;
        $this->description = $description;
        $this->kpi = $kpi;
        $this->target_page_id = $target_page_id;
        $this->state = $state;
        $this->type = $type;
        $this->save();
    }

    public function canStart()
    {
        if ($this->kpi && $this->name) {
            $running_test = self::where('post_id', $this->post_id)->where('state', AB_TEST_STATE_RUNNING)->first();
            if($running_test)
                return array(
                    'can' => false,
                    'message' => esc_html__("You can't run multiple test on a content.", 'robera-ab-test')
                );
            return array(
                'can' => true
            );
        }
        return array(
            'can' => false,
            'message' => esc_html__("You should complete the test info first.", 'robera-ab-test')
        );
    }
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

    /**
     * Disable created_at and update_at columns, unless you have those.
     */
    public $timestamps = true;
}
