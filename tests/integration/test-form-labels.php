<?php
/**
 * Verifies that the Form post type speaks of Forms, not Posts, in the admin.
 *
 * @package JayPee\FormBuilder
 */

declare( strict_types = 1 );

final class Test_Form_Labels extends WP_UnitTestCase {

    public function test_post_type_labels_name_forms(): void {
        $labels = get_post_type_object( 'jp_form' )->labels;

        $this->assertSame( 'Add Form', $labels->add_new );
        $this->assertSame( 'Add Form', $labels->add_new_item );
        $this->assertSame( 'Edit Form', $labels->edit_item );
        $this->assertSame( 'No Forms found.', $labels->not_found );
        $this->assertSame( 'Form published.', $labels->item_published );
        $this->assertSame( 'Form updated.', $labels->item_updated );
    }

    public function test_bulk_messages_name_forms(): void {
        $counts = [
            'updated'   => 1,
            'locked'    => 1,
            'deleted'   => 2,
            'trashed'   => 1,
            'untrashed' => 1,
        ];

        $messages = apply_filters( 'bulk_post_updated_messages', [], $counts );

        $this->assertSame( '%s Form moved to the Trash.', $messages['jp_form']['trashed'] );
        $this->assertSame( '%s Forms permanently deleted.', $messages['jp_form']['deleted'] );
    }
}
