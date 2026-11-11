<?php

namespace Tests\Feature\Tickets;

use App\Rules\RichTextBody;
use App\Support\RichText;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RichTextTest extends TestCase
{
    public function test_keeps_basic_formatting(): void
    {
        $html = '<p class="ql-align-center"><strong>Hola</strong> <em>mundo</em> <u>sub</u> <s>tachado</s></p><ul><li>uno</li></ul><ol><li>dos</li></ol>';

        $clean = RichText::sanitize($html);

        $this->assertStringContainsString('<p class="ql-align-center">', $clean);
        $this->assertStringContainsString('<strong>Hola</strong>', $clean);
        $this->assertStringContainsString('<em>mundo</em>', $clean);
        $this->assertStringContainsString('<u>sub</u>', $clean);
        $this->assertStringContainsString('<s>tachado</s>', $clean);
        $this->assertStringContainsString('<ul><li>uno</li></ul>', $clean);
        $this->assertStringContainsString('<ol><li>dos</li></ol>', $clean);
    }

    public function test_removes_scripts_handlers_and_dangerous_links(): void
    {
        $html = '<p onclick="alert(1)">x</p><script>alert(1)</script><img src=x onerror="alert(1)">'
            .'<a href="javascript:alert(1)">malo</a><iframe src="https://evil.test"></iframe>';

        $clean = RichText::sanitize($html);

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert(1)</script>', $clean);
        $this->assertStringNotContainsString('<img', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
    }

    public function test_links_open_in_new_tab_safely(): void
    {
        $clean = RichText::sanitize('<p><a href="https://wortev.com">sitio</a></p>');

        $this->assertStringContainsString('href="https://wortev.com"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $clean);
        $this->assertStringContainsString('target="_blank"', $clean);
    }

    public function test_only_quill_classes_survive(): void
    {
        $clean = RichText::sanitize('<p class="ql-indent-1 fixed inset-0 z-50">x</p><span class="ql-size-large bg-red-500">y</span>');

        $this->assertStringContainsString('<p class="ql-indent-1">', $clean);
        $this->assertStringContainsString('<span class="ql-size-large">', $clean);
        $this->assertStringNotContainsString('fixed', $clean);
        $this->assertStringNotContainsString('bg-red-500', $clean);
    }

    public function test_plain_text_is_escaped_and_keeps_line_breaks(): void
    {
        $html = RichText::toHtml("Hola\n<b onclick=x>no es html del editor");

        // Se detecta como HTML por la etiqueta <b>, así que se sanitiza (sin el onclick).
        $this->assertStringNotContainsString('onclick', $html);

        $this->assertSame('Línea 1<br>'."\n".'Dato &lt; 5', RichText::toHtml("Línea 1\nDato < 5"));
    }

    public function test_validation_rejects_empty_editor_content(): void
    {
        $rules = ['body' => ['required', 'string', new RichTextBody]];

        $this->assertTrue(Validator::make(['body' => '<p><br></p>'], $rules)->fails());
        $this->assertTrue(Validator::make(['body' => '<p> &nbsp; </p>'], $rules)->fails());
        $this->assertTrue(Validator::make(['body' => '<p>'.str_repeat('a', RichText::MAX_TEXT_LENGTH + 1).'</p>'], $rules)->fails());
        $this->assertFalse(Validator::make(['body' => '<p><strong>Listo</strong></p>'], $rules)->fails());
        $this->assertFalse(Validator::make(['body' => 'Texto plano'], $rules)->fails());
    }
}
