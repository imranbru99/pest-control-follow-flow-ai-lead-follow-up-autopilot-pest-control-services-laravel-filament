<?php

namespace App\Services;

use App\Models\Lead;

/**
 * Local equivalent of imrandevbd/laravel-ai-hub — draft/classify/extract for pest control lead follow-ups.
 */
class AiHubService
{
    public function draftFollowUp(Lead $lead, string $channel = 'email'): array
    {
        $interest = $lead->service_interest ?: 'pest control service';
        $name = $lead->name;

        $subject = match ($channel) {
            'sms' => null,
            default => "Quick follow-up on your {$interest} request — PestControlFollowFlow",
        };

        $body = match ($channel) {
            'sms' => "Hi {$name}, this is your pest control team following up on your {$interest} inquiry. Reply YES to book an inspection, or STOP to opt out.",
            default => "Hi {$name},\n\nThanks for reaching out about {$interest}. Our licensed technicians can help with termite inspections, rodent exclusion, mosquito treatments, bed bugs, and general pest control.\n\nReply to this email or call us to schedule a convenient inspection window. If you prefer not to hear from us, reply STOP and we will remove you immediately.\n\n— Your PestControlFollowFlow team",
        };

        $classification = $this->classifyLead($lead);

        return [
            'subject' => $subject,
            'body' => $body,
            'classification' => $classification['label'],
            'confidence' => $classification['confidence'],
        ];
    }

    public function classifyLead(Lead $lead): array
    {
        $text = strtolower(($lead->service_interest ?? '').' '.($lead->notes ?? '').' '.($lead->source ?? ''));

        if (str_contains($text, 'emergency') || str_contains($text, 'infestation') || str_contains($text, 'swarm')) {
            return ['label' => 'urgent_qualified', 'confidence' => 96.5];
        }

        if (str_contains($text, 'termite') || str_contains($text, 'bed bug') || str_contains($text, 'rodent')) {
            return ['label' => 'high_value', 'confidence' => 91.0];
        }

        if (str_contains($text, 'quote') || str_contains($text, 'estimate') || str_contains($text, 'inspection')) {
            return ['label' => 'estimate_ready', 'confidence' => 88.0];
        }

        return ['label' => 'nurture', 'confidence' => 74.5];
    }

    public function classifyReply(string $reply): array
    {
        $text = strtolower($reply);

        if (preg_match('/\b(yes|interested|book|schedule|tomorrow|available)\b/', $text)) {
            return ['label' => 'qualified_hot', 'confidence' => 94.0, 'escalate' => true];
        }

        if (preg_match('/\b(stop|unsubscribe|remove|no)\b/', $text)) {
            return ['label' => 'opt_out', 'confidence' => 99.0, 'escalate' => false];
        }

        return ['label' => 'neutral', 'confidence' => 70.0, 'escalate' => false];
    }

    public function extractServiceSignals(string $text): array
    {
        $signals = [];
        foreach (['termite', 'bed bug', 'rodent', 'mosquito', 'ant', 'cockroach', 'wasp', 'inspection', 'exclusion'] as $term) {
            if (stripos($text, $term) !== false) {
                $signals[] = $term;
            }
        }

        return $signals;
    }
}
