<?php

namespace MM\Meros\App\Components\FieldGroups;

use MM\Meros\Contracts\Features\Components\FieldGroup;

class SimpleContact extends FieldGroup {
    protected function configure(): void {
        $this->id('simple-contact-fields');
        $this->title('Simple Contact Form');
        $this->description('A simple contact form with name, email, and message fields.');

        $this->row(function ($row) {
            $row->field('text', function ($field) {
                $field->id('first-name');
                $field->label('First Name');
                $field->placeholder('Your first name');
                $field->required(true);
            });

            $row->field('text', function ($field) {
                $field->id('last-name');
                $field->label('Last Name');
                $field->placeholder('Your last name');
                $field->required(true);
            });
        });

        $this->row(function ($row) {
            $row->field('email', function ($field) {
                $field->id('email');
                $field->label('Email Address');
                $field->placeholder('e.g. user@example.com');
                $field->required(true);
            });
        });

        $this->row(function ($row) {
            $row->field('text', function ($field) {
                $field->id('message');
                $field->label('Message');
                $field->required(true);
            });
        });
    }
}