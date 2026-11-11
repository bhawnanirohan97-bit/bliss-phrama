<?php

namespace App\Services\Drafting;

use App\Enums\DraftChannel;
use Illuminate\Support\Str;

/**
 * The versioned, deterministic templates behind every draft.
 *
 * The wording is deliberately conservative. Bliss Pharmex is a third-party
 * exporter: the templates ask for the buyer's exact requirement and offer a
 * quotation, and never claim to manufacture, own a brand, hold stock or carry
 * a certification. That restriction is enforced again by the fact checker, so
 * a careless edit to a template cannot reach the operator unannounced.
 */
final class DraftTemplates
{
    public const VERSION = '1.0.0';

    public function render(DraftChannel $channel, DraftContext $context): RenderedDraft
    {
        return match ($channel) {
            DraftChannel::Whatsapp => new RenderedDraft(
                subject: null,
                body: $this->whatsapp($context),
                templateId: 'whatsapp.first_response',
                templateVersion: self::VERSION,
            ),
            DraftChannel::Email => new RenderedDraft(
                subject: $this->subject($context),
                body: $this->email($context),
                templateId: 'email.first_response',
                templateVersion: self::VERSION,
            ),
            DraftChannel::Call => new RenderedDraft(
                subject: null,
                body: $this->callOpener($context),
                templateId: 'call.first_opener',
                templateVersion: self::VERSION,
            ),
        };
    }

    private function subject(DraftContext $context): string
    {
        if ($context->product !== null) {
            return Str::limit($context->product->name, 70, '…').' — quotation for your enquiry';
        }

        if ($context->requirement !== null) {
            return 'Your '.Str::limit($context->requirement, 80, '…').' enquiry — '.$context->company;
        }

        return 'Your IndiaMART enquiry — '.$context->company;
    }

    private function email(DraftContext $context): string
    {
        return $this->paragraphs([
            'Dear '.$context->emailGreeting().',',
            'Thank you for '.$context->enquiryPhrase().$context->quantitySuffix().' on IndiaMART.',
            'We are '.$context->company.', a third-party pharmaceutical exporter based in '
                .$context->city.', '.$context->country.'. We supply finished formulations from '
                .'authorised manufacturing principals and support export documentation for the '
                .'destination market.',
            $this->productLine($context),
            "To prepare an accurate quotation, could you please confirm:\n"
                ."• the exact product, strength and pack size you need;\n"
                ."• the quantity for your first order;\n"
                ."• the destination country and port; and\n"
                .'• any registration or documentation requirements for your market.',
            'Once we have these details, we will send you our offer and the documentation we can provide.',
            "Kind regards,\n".$context->desk."\n".$context->city.', '.$context->country
                .($this->contactLine($context) === '' ? '' : "\n".$this->contactLine($context)),
        ]);
    }

    private function whatsapp(DraftContext $context): string
    {
        $productLine = $this->whatsappProductLine($context);

        return $this->paragraphs([
            'Hi '.$context->addressedName().',',
            'This is the export desk at '.$context->company.', a third-party pharmaceutical exporter in '
                .$context->city.', '.$context->country.'. Thank you for '
                .$context->enquiryPhrase().$context->quantitySuffix().' on IndiaMART.',
            $productLine,
            $context->product === null
                ? 'Once we have it, we will confirm what we can supply and send our quotation.'
                : 'Could you share the exact product, strength and pack size you need, the quantity you '
                    .'need, and the destination country? We will then send you our quotation and '
                    .'documentation details.',
            '— '.$context->desk.($context->phone === '' ? '' : "\n".$context->phone),
        ]);
    }

    private function callOpener(DraftContext $context): string
    {
        return $this->paragraphs([
            'Hi '.$context->addressedName().', this is the export desk at '.$context->company
                .', a third-party pharmaceutical exporter in '.$context->city.', '.$context->country
                .'. I am calling about '.$context->enquiryPhrase().$context->quantitySuffix()
                .' on IndiaMART. Do you have two minutes?',
            'I would like to confirm the exact product, strength and pack size you need, the quantity for '
                .'your first order, and the destination country and port. With those details I can send '
                .'you an accurate quotation and the documentation we can provide.',
            $this->callProductLine($context),
        ]);
    }

    private function productLine(DraftContext $context): string
    {
        if ($context->product === null) {
            return 'Please share your full product list with strengths and pack sizes.';
        }

        return 'For this requirement we can quote '.$context->product->name
            .$context->productSuffix().'.';
    }

    private function whatsappProductLine(DraftContext $context): string
    {
        if ($context->product === null) {
            return 'Please send your product list with strengths and pack sizes.';
        }

        return 'We can quote '.$context->product->name.$context->productSuffix()
            .' for this requirement.';
    }

    private function callProductLine(DraftContext $context): string
    {
        if ($context->product === null) {
            return 'If you have a product list, I can note the items and confirm which ones we can supply.';
        }

        return 'For reference, the item we can quote is '.$context->product->name
            .$context->productSuffix().'.';
    }

    private function contactLine(DraftContext $context): string
    {
        return implode(' | ', array_filter([$context->phone, $context->email], fn (string $part) => $part !== ''));
    }

    /**
     * @param  list<string>  $paragraphs
     */
    private function paragraphs(array $paragraphs): string
    {
        return implode("\n\n", array_filter($paragraphs, fn (string $paragraph) => trim($paragraph) !== ''));
    }
}
