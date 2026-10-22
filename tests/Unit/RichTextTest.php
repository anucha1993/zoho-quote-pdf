<?php

namespace Tests\Unit;

use App\Support\RichText;
use PHPUnit\Framework\TestCase;

class RichTextTest extends TestCase
{
    public function test_plain_text_is_escaped_and_newlines_become_br(): void
    {
        $this->assertSame("1. ราคา &lt;100&gt;<br />\n2. ชำระ", RichText::block("1. ราคา <100>\n2. ชำระ"));
    }

    public function test_zoho_rich_text_keeps_formatting_but_drops_attributes(): void
    {
        $html = '<span style="color:rgb(0, 0, 0);background-color:rgba(0, 0, 0, 0);"><b>ใบเสนอราคา แจ้งออก</b></span>';

        $this->assertSame('<span><b>ใบเสนอราคา แจ้งออก</b></span>', RichText::inline($html));
    }

    public function test_block_keeps_lists_and_underline(): void
    {
        $html = '<b><u>เงื่อนไข</u></b><br><ol style="list-style-type:decimal;padding-inline-start:20px;">'
            .'<li style="list-style-image:none;"><p style="display:inline;"><span style="font-weight:normal;">อัตรานี้รวมค่าจัดทำเอกสาร</span></p></li></ol>';

        $this->assertSame(
            '<b><u>เงื่อนไข</u></b><br /><ol><li><span>อัตรานี้รวมค่าจัดทำเอกสาร</span></li></ol>',
            RichText::block($html)
        );
    }

    public function test_inline_strips_block_tags_and_dangerous_content(): void
    {
        $html = '<p>หัวข้อ</p><script>alert(1)</script><img src=x onerror="x"><a href="http://x" onclick="y">ลิงก์</a>';

        $this->assertSame('หัวข้อลิงก์', RichText::inline($html));
    }

    public function test_plain_converts_html_to_text(): void
    {
        $this->assertSame("ก\nข", RichText::plain('<p>ก</p><p>ข</p>'));
        $this->assertSame('ธรรมดา', RichText::plain('ธรรมดา'));
    }
}
