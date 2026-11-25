<?php

namespace MM\Meros\Tests\Integration;

use Illuminate\Support\Facades\Date;
use MM\Meros\Facades\Components\Forms;
use MM\Meros\Facades\Theme;
use MM\Meros\Testing\WordPressTestCase;

final class FormSubmissionTest extends WordPressTestCase
{
    public function test_form_submission_builds_a_timestamped_payload_without_acorn_helpers(): void
    {
        $form = Forms::checkout(Theme::get())->make(['title' => 'Contact']);
        Date::setTestNow('2026-07-01 12:00:00 UTC');
        try {
            $payload = $form->buildSubmissionPayload(['name' => 'Test', 'internal' => 7], ['internal']);
            $this->assertSame('Contact', $payload['form_title']);
            $this->assertNotEmpty($payload['form_id']);
            $this->assertSame('2026-07-01T12:00:00+00:00', $payload['submission_time']->toIso8601String());
            $this->assertSame(get_current_user_id(), $payload['user_id']);
            $this->assertSame([
                'name' => [
                    'field_name' => 'name', 'user_value' => 'Test',
                    'user_value_data_type' => 'string', 'submission_state' => 'shown',
                ],
                'internal' => [
                    'field_name' => 'internal', 'user_value' => 7,
                    'user_value_data_type' => 'integer', 'submission_state' => 'hidden',
                ],
            ], $payload['data']);
        } finally {
            Date::setTestNow();
        }
    }
}
