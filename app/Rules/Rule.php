<?php
namespace WaasKit\FluentBooking\Rules;
interface Rule
{
    public function id(): string;
    /** Return a human-readable refusal, or null. No WordPress dependency. */
    public function validate(array $context, array $settings): ?string;
}
