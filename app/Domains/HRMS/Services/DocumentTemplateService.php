<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\DocumentTemplate;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeFnfSettlement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DocumentTemplateService
{
    /**
     * Import raw content from an uploaded template file (.html, .htm, .txt, .docx).
     */
    public function importTemplateFromFile($uploadedFile): string
    {
        if (!$uploadedFile || !$uploadedFile->isValid()) {
            return '';
        }

        $extension = strtolower($uploadedFile->getClientOriginalExtension());

        if (in_array($extension, ['html', 'htm', 'txt'])) {
            return file_get_contents($uploadedFile->getRealPath()) ?: '';
        }

        if ($extension === 'docx') {
            return $this->parseDocxToHtml($uploadedFile->getRealPath());
        }

        return file_get_contents($uploadedFile->getRealPath()) ?: '';
    }

    /**
     * Parse complete DOCX (Word OpenXML) into clean HTML preserving formatting, tables, styles, alignment & images.
     */
    public function parseDocxToHtml(string $filePath): string
    {
        if (!file_exists($filePath) || !class_exists('ZipArchive')) {
            return '';
        }

        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            return '';
        }

        // 1. Read Relationships to extract image / media links
        $mediaMap = [];
        if (($relsIndex = $zip->locateName('word/_rels/document.xml.rels')) !== false) {
            $relsXml = $zip->getFromIndex($relsIndex);
            if ($relsXml) {
                try {
                    $relsDom = new \DOMDocument();
                    @$relsDom->loadXML($relsXml);
                    foreach ($relsDom->getElementsByTagName('Relationship') as $rel) {
                        $id = $rel->getAttribute('Id');
                        $target = $rel->getAttribute('Target');
                        $type = $rel->getAttribute('Type');
                        if (str_contains($type, '/image') || preg_match('/\.(png|jpg|jpeg|gif|webp|svg)$/i', $target)) {
                            $targetPath = str_starts_with($target, 'media/') ? ('word/' . $target) : (str_starts_with($target, 'word/') ? $target : ('word/' . ltrim($target, '/')));
                            if (($imgIndex = $zip->locateName($targetPath)) !== false) {
                                $imgData = $zip->getFromIndex($imgIndex);
                                $ext = pathinfo($target, PATHINFO_EXTENSION) ?: 'png';
                                $mime = match (strtolower($ext)) {
                                    'jpg', 'jpeg' => 'image/jpeg',
                                    'png' => 'image/png',
                                    'gif' => 'image/gif',
                                    'webp' => 'image/webp',
                                    'svg' => 'image/svg+xml',
                                    default => 'image/png'
                                };
                                $mediaMap[$id] = 'data:' . $mime . ';base64,' . base64_encode($imgData);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("DOCX rels parsing error: " . $e->getMessage());
                }
            }
        }

        // 2. Read Main Document XML
        $docIndex = $zip->locateName('word/document.xml');
        if ($docIndex === false) {
            $zip->close();
            return '';
        }

        $documentXml = $zip->getFromIndex($docIndex);
        $zip->close();

        if (empty($documentXml)) {
            return '';
        }

        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = true;
            @$dom->loadXML($documentXml);

            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
            $xpath->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
            $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

            $bodyNodes = $xpath->query('//w:body/*');
            if (!$bodyNodes || $bodyNodes->length === 0) {
                return '';
            }

            $html = '';
            foreach ($bodyNodes as $node) {
                $html .= $this->convertDocxNodeToHtml($node, $xpath, $mediaMap);
            }

            return trim($html);
        } catch (\Throwable $e) {
            Log::warning("DOCX to HTML parsing error: " . $e->getMessage());
            // Fallback
            $xml = strip_tags($documentXml, '<w:p><w:r><w:t>');
            $text = preg_replace('/<w:p[^>]*>/', "<p>", $xml);
            $text = preg_replace('/<\/w:p>/', "</p>", $text);
            return strip_tags($text, '<p>');
        }
    }

    /**
     * Convert an individual OpenXML node (w:p, w:tbl, etc.) to semantic HTML.
     */
    protected function convertDocxNodeToHtml(\DOMNode $node, \DOMXPath $xpath, array $mediaMap): string
    {
        $nodeName = $node->localName ?? $node->nodeName;

        if ($nodeName === 'p') {
            return $this->convertDocxParagraphToHtml($node, $xpath, $mediaMap);
        }

        if ($nodeName === 'tbl') {
            return $this->convertDocxTableToHtml($node, $xpath, $mediaMap);
        }

        return '';
    }

    /**
     * Convert a Word paragraph (w:p) to HTML (<p>, <h1>, <h2>, etc.) with exact styles.
     */
    protected function convertDocxParagraphToHtml(\DOMNode $pNode, \DOMXPath $xpath, array $mediaMap): string
    {
        $tag = 'p';
        $classes = [];
        $styles = [];

        // Check paragraph properties (w:pPr)
        $pPr = $xpath->query('w:pPr', $pNode)->item(0);
        if ($pPr) {
            // Heading style
            $pStyle = $xpath->query('w:pStyle/@w:val', $pPr)->item(0);
            if ($pStyle) {
                $styleVal = strtolower($pStyle->nodeValue);
                if (str_contains($styleVal, 'heading1') || str_contains($styleVal, 'title')) {
                    $tag = 'h1';
                } elseif (str_contains($styleVal, 'heading2') || str_contains($styleVal, 'subtitle')) {
                    $tag = 'h2';
                } elseif (str_contains($styleVal, 'heading3')) {
                    $tag = 'h3';
                } elseif (str_contains($styleVal, 'heading4')) {
                    $tag = 'h4';
                }
            }

            // Alignment (support both Quill class and CSS text-align for preview/PDF)
            $jc = $xpath->query('w:jc/@w:val', $pPr)->item(0);
            if ($jc) {
                $align = match (strtolower($jc->nodeValue)) {
                    'center' => 'center',
                    'right' => 'right',
                    'both', 'distribute', 'justify' => 'justify',
                    default => 'left'
                };
                if ($align !== 'left') {
                    $classes[] = 'ql-align-' . $align;
                    $styles[] = "text-align: {$align};";
                }
            }
        }

        // Parse child runs, linebreaks, hyperlinks, images
        $innerHtml = '';
        foreach ($pNode->childNodes as $child) {
            $childName = $child->localName ?? $child->nodeName;

            if ($childName === 'r') {
                $innerHtml .= $this->convertDocxRunToHtml($child, $xpath, $mediaMap);
            } elseif ($childName === 'hyperlink') {
                $linkText = '';
                foreach ($child->childNodes as $hyperChild) {
                    if (($hyperChild->localName ?? $hyperChild->nodeName) === 'r') {
                        $linkText .= $this->convertDocxRunToHtml($hyperChild, $xpath, $mediaMap);
                    }
                }
                $innerHtml .= "<a href=\"#\" target=\"_blank\">{$linkText}</a>";
            }
        }

        // If paragraph is empty or only whitespace, return a single break paragraph for Quill parity
        if (trim(strip_tags($innerHtml, '<img><br>')) === '' && !str_contains($innerHtml, '<img') && !str_contains($innerHtml, '<br>')) {
            return "<p><br></p>\n";
        }

        $classAttr = !empty($classes) ? (' class="' . implode(' ', $classes) . '"') : '';
        $styleAttr = !empty($styles) ? (' style="' . implode(' ', $styles) . '"') : '';
        return "<{$tag}{$classAttr}{$styleAttr}>{$innerHtml}</{$tag}>\n";
    }

    /**
     * Convert a Word text run (w:r) to HTML formatting (bold, italic, underline, color, size, font, text, br, images).
     */
    protected function convertDocxRunToHtml(\DOMNode $rNode, \DOMXPath $xpath, array $mediaMap): string
    {
        $isBold = false;
        $isItalic = false;
        $isUnderline = false;
        $isStrike = false;
        $isSup = false;
        $isSub = false;
        $spanStyles = [];

        $rPr = $xpath->query('w:rPr', $rNode)->item(0);
        if ($rPr) {
            if ($xpath->query('w:b[not(@w:val) or @w:val="1" or @w:val="true"]', $rPr)->length > 0) {
                $isBold = true;
            }
            if ($xpath->query('w:i[not(@w:val) or @w:val="1" or @w:val="true"]', $rPr)->length > 0) {
                $isItalic = true;
            }
            if ($xpath->query('w:u[not(@w:val="none")]', $rPr)->length > 0) {
                $isUnderline = true;
            }
            if ($xpath->query('w:strike[not(@w:val) or @w:val="1" or @w:val="true"]', $rPr)->length > 0) {
                $isStrike = true;
            }

            // Subscript / Superscript
            $vertAlign = $xpath->query('w:vertAlign/@w:val', $rPr)->item(0);
            if ($vertAlign) {
                if ($vertAlign->nodeValue === 'superscript') {
                    $isSup = true;
                } elseif ($vertAlign->nodeValue === 'subscript') {
                    $isSub = true;
                }
            }

            // Font Color
            $color = $xpath->query('w:color/@w:val', $rPr)->item(0);
            if ($color && $color->nodeValue && $color->nodeValue !== 'auto') {
                $spanStyles[] = 'color: #' . ltrim($color->nodeValue, '#') . ';';
            }

            // Highlight / Background color
            $highlight = $xpath->query('w:highlight/@w:val', $rPr)->item(0);
            if ($highlight && $highlight->nodeValue && $highlight->nodeValue !== 'none') {
                $spanStyles[] = 'background-color: ' . htmlspecialchars($highlight->nodeValue) . ';';
            }

            // Font Family
            $font = $xpath->query('w:rFonts/@w:ascii', $rPr)->item(0);
            if ($font && $font->nodeValue && !in_array(strtolower($font->nodeValue), ['calibri', 'arial'])) {
                $spanStyles[] = 'font-family: ' . htmlspecialchars($font->nodeValue) . ', sans-serif;';
            }

            // Font Size (stored in half-points: 24 = 12pt)
            $sz = $xpath->query('w:sz/@w:val', $rPr)->item(0);
            if ($sz && is_numeric($sz->nodeValue)) {
                $pt = ((int)$sz->nodeValue) / 2;
                if ($pt >= 8 && $pt <= 48) {
                    $spanStyles[] = "font-size: {$pt}pt;";
                }
            }
        }

        $text = '';
        foreach ($rNode->childNodes as $child) {
            $childName = $child->localName ?? $child->nodeName;

            if ($childName === 't') {
                $escaped = htmlspecialchars($child->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                // Preserve consecutive spaces without breaking normal word wrap
                $text .= preg_replace('/ {2}/', '&nbsp; ', $escaped);
            } elseif ($childName === 'br' || $childName === 'cr') {
                $text .= '<br>';
            } elseif ($childName === 'tab') {
                $text .= '&nbsp;&nbsp;&nbsp;&nbsp;';
            } elseif ($childName === 'noBreakHyphen') {
                $text .= '&#8209;';
            } elseif ($childName === 'drawing' || $childName === 'pict') {
                // DrawingML or VML Embedded Image
                $blip = $xpath->query('.//a:blip/@r:embed', $child)->item(0);
                if (!$blip) {
                    $blip = $xpath->query('.//v:imagedata/@r:id', $child)->item(0);
                }
                if ($blip && isset($mediaMap[$blip->nodeValue])) {
                    $imgSrc = $mediaMap[$blip->nodeValue];
                    $text .= '<img src="' . $imgSrc . '" style="max-width:100%; height:auto; margin:4px 0;" alt="Embedded Image" />';
                }
            }
        }

        if ($text === '') {
            return '';
        }

        if (!empty($spanStyles)) {
            $text = '<span style="' . implode(' ', $spanStyles) . '">' . $text . '</span>';
        }
        if ($isSub) {
            $text = '<sub>' . $text . '</sub>';
        }
        if ($isSup) {
            $text = '<sup>' . $text . '</sup>';
        }
        if ($isStrike) {
            $text = '<s>' . $text . '</s>';
        }
        if ($isUnderline) {
            $text = '<u>' . $text . '</u>';
        }
        if ($isItalic) {
            $text = '<em>' . $text . '</em>';
        }
        if ($isBold) {
            $text = '<strong>' . $text . '</strong>';
        }

        return $text;
    }

    /**
     * Convert a Word table (w:tbl) to HTML table preserving borders, shading and cell padding.
     */
    protected function convertDocxTableToHtml(\DOMNode $tblNode, \DOMXPath $xpath, array $mediaMap): string
    {
        $rows = $xpath->query('w:tr', $tblNode);
        if ($rows->length === 0) {
            return '';
        }

        $html = '<table style="width:100%; border-collapse:collapse; margin:12px 0; border:1px solid #cbd5e1;">' . "\n";
        $isFirstRow = true;

        foreach ($rows as $tr) {
            $html .= '  <tr>' . "\n";
            $cells = $xpath->query('w:tc', $tr);

            foreach ($cells as $tc) {
                $cellTag = $isFirstRow ? 'th' : 'td';
                $cellStyles = ['border: 1px solid #cbd5e1;', 'padding: 6px 10px;', 'vertical-align: top;'];
                
                // Cell background shading
                $tcPr = $xpath->query('w:tcPr', $tc)->item(0);
                if ($tcPr) {
                    $shd = $xpath->query('w:shd/@w:fill', $tcPr)->item(0);
                    if ($shd && $shd->nodeValue && $shd->nodeValue !== 'auto') {
                        $cellStyles[] = 'background-color: #' . ltrim($shd->nodeValue, '#') . ';';
                    } elseif ($isFirstRow) {
                        $cellStyles[] = 'background-color: #f8fafc;';
                    }

                    $vAlign = $xpath->query('w:vAlign/@w:val', $tcPr)->item(0);
                    if ($vAlign && $vAlign->nodeValue) {
                        $cellStyles[] = 'vertical-align: ' . match($vAlign->nodeValue) {
                            'center' => 'middle',
                            'bottom' => 'bottom',
                            default => 'top'
                        } . ';';
                    }
                } elseif ($isFirstRow) {
                    $cellStyles[] = 'background-color: #f8fafc;';
                }

                if ($isFirstRow) {
                    $cellStyles[] = 'font-weight: 600;';
                }

                $cellContent = '';
                $pNodes = $xpath->query('w:p', $tc);
                foreach ($pNodes as $p) {
                    $cellContent .= $this->convertDocxParagraphToHtml($p, $xpath, $mediaMap);
                }

                $styleAttr = ' style="' . implode(' ', $cellStyles) . '"';
                $html .= "    <{$cellTag}{$styleAttr}>" . trim($cellContent) . "</{$cellTag}>\n";
            }

            $html .= '  </tr>' . "\n";
            $isFirstRow = false;
        }

        $html .= '</table>' . "\n";
        return $html;
    }

    /**
     * Render complete template HTML for an employee by substituting dynamic tags.
     * When $employee is null, placeholders are preserved for clean template live preview.
     */
    public function renderTemplate(DocumentTemplate $template, ?Employee $employee = null, ?string $refNumber = null, array $extraData = []): string
    {
        $company = null;

        if ($employee) {
            $employee->loadMissing(['company', 'department', 'designation', 'branch', 'reportingManager']);
            $company = $employee->company ?: Company::first();
        } else {
            $company = Company::first();
        }

        // 1. Assemble Header, Body, and Footer
        $header = $template->header_content ?? '';
        $body = $template->body_content ?? '';
        $footer = $template->footer_content ?? '';

        $fullHtml = '';
        if ($header) {
            $fullHtml .= '<header class="doc-header mb-3">' . $header . '</header>';
        }
        $fullHtml .= '<main class="doc-body">' . $body . '</main>';
        if ($footer) {
            $fullHtml .= '<footer class="doc-footer mt-4 pt-3 border-top">' . $footer . '</footer>';
        }

        if ($employee) {
            $refNo = $refNumber ?: ('DOC/' . ($company?->code ?? 'ORG') . '/' . date('Y') . '/' . str_pad((string)$employee->id, 4, '0', STR_PAD_LEFT));

            // Resolve HR Signature Data
            $hrName = $extraData['hr_name'] ?? auth()->user()?->name ?? 'Authorized HR Signatory';
            $hrDesignation = $extraData['hr_designation'] ?? 'HR Manager';
            $issueDateStr = !empty($extraData['issue_date']) ? Carbon::parse($extraData['issue_date'])->format('d M, Y') : Carbon::today()->format('d M, Y');
            $hrSigUrl = $extraData['hr_signature_url'] ?? $extraData['hr_signature_data'] ?? null;

            if ($hrSigUrl) {
                $hrSigHtml = '<div style="display:inline-block; text-align:left; margin:5px 0;">' .
                             '<img src="' . $hrSigUrl . '" style="max-height:55px; max-width:200px; object-fit:contain; display:block;" alt="HR Signature" />' .
                             '</div>';
            } else {
                $hrSigHtml = '<div style="display:inline-block; border-bottom:1.5px solid #0f172a; width:180px; height:35px; text-align:center; color:#94a3b8; font-size:11px; line-height:45px;">[ Signature Line ]</div>';
            }

            // Salary and Compensation Calculations
            $salary = (float)($employee->current_salary ?? 0);
            $annualCtc = $salary > 0 ? ('₹' . number_format($salary, 2)) : 'N/A';
            $monthlyGross = $salary > 0 ? ('₹' . number_format($salary / 12, 2)) : 'N/A';
            $basicSalary = $salary > 0 ? ('₹' . number_format(($salary / 12) * 0.5, 2)) : 'N/A';
            $salaryInWords = $salary > 0 ? ($this->numberToWords($salary) . ' Only') : 'N/A';

            // Tenure & Conduct calculations
            $doj = $employee->date_of_joining ? Carbon::parse($employee->date_of_joining) : Carbon::today();
            $lwd = (isset($employee->relieving_date) && $employee->relieving_date) ? Carbon::parse($employee->relieving_date) : Carbon::today();
            $diff = $doj->diff($lwd);
            $tenureString = trim(($diff->y ? $diff->y . ' year' . ($diff->y > 1 ? 's ' : ' ') : '') . ($diff->m ? $diff->m . ' month' . ($diff->m > 1 ? 's' : '') : ''));
            if (empty($tenureString)) {
                $tenureString = $diff->days . ' days';
            }

            // Build Employee Substitution Dictionary
            $dictionary = [
                '{{employee_name}}'         => e($employee->full_name ?? ''),
                '{{employee_id}}'           => e($employee->employee_id ?? ''),
                '{{email}}'                 => e($employee->personal_email ?? $employee->office_email ?? 'N/A'),
                '{{phone}}'                 => e($employee->personal_mobile_number ?? $employee->home_phone ?? 'N/A'),
                '{{dob}}'                   => $employee->date_of_birth ? Carbon::parse($employee->date_of_birth)->format('d M, Y') : 'N/A',
                '{{gender}}'                => e(ucfirst($employee->gender ?? 'N/A')),
                '{{marital_status}}'        => e(ucfirst($employee->marital_status ?? 'N/A')),
                '{{blood_group}}'           => e($employee->blood_group ?? 'N/A'),
                '{{current_address}}'       => e($employee->present_address ?? 'N/A'),
                '{{permanent_address}}'     => e($employee->permanent_address ?? 'N/A'),
                '{{emergency_contact_name}}' => e($employee->emergency_contact_name ?? 'N/A'),
                '{{emergency_contact_phone}}'=> e($employee->emergency_contact_number ?? 'N/A'),

                '{{designation}}'           => e($employee->designation?->name ?? $employee->job_title ?? 'N/A'),
                '{{department}}'            => e($employee->department?->name ?? 'N/A'),
                '{{branch}}'                => e($employee->branch?->name ?? 'N/A'),
                '{{reporting_manager}}'     => e($employee->reportingManager?->full_name ?? 'N/A'),
                '{{joining_date}}'          => $employee->date_of_joining ? Carbon::parse($employee->date_of_joining)->format('d M, Y') : 'N/A',
                '{{date_of_joining}}'       => $employee->date_of_joining ? Carbon::parse($employee->date_of_joining)->format('d M, Y') : 'N/A',
                '{{probation_end_date}}'    => $employee->probation_end_date ? Carbon::parse($employee->probation_end_date)->format('d M, Y') : 'N/A',
                '{{confirmation_date}}'     => $employee->confirmation_date ? Carbon::parse($employee->confirmation_date)->format('d M, Y') : 'N/A',
                '{{last_working_day}}'      => (isset($employee->relieving_date) && $employee->relieving_date) ? Carbon::parse($employee->relieving_date)->format('d M, Y') : Carbon::today()->format('d M, Y'),
                '{{relieving_date}}'        => (isset($employee->relieving_date) && $employee->relieving_date) ? Carbon::parse($employee->relieving_date)->format('d M, Y') : Carbon::today()->format('d M, Y'),
                '{{resignation_date}}'      => (isset($employee->resignation_date) && $employee->resignation_date) ? Carbon::parse($employee->resignation_date)->format('d M, Y') : 'N/A',
                '{{employment_status}}'     => e(ucfirst(str_replace('_', ' ', $employee->employee_stage ?? $employee->employment_type ?? 'active'))),
                '{{separation_type}}'       => e(ucfirst(str_replace('_', ' ', $employee->separation_type ?? 'Resignation'))),
                '{{tenure_string}}'         => e($tenureString),
                '{{conduct_statement}}'     => 'During their tenure, we found their character, dedication, and professional conduct to be exemplary.',
                '{{clearance_status}}'      => 'All company assets, dues, files, and accounts have been fully cleared across IT, Finance, HR, Admin, and Operations departments.',
                
                // Salary & Compensation
                '{{annual_ctc}}'            => $annualCtc,
                '{{ctc}}'                   => $annualCtc,
                '{{monthly_gross_salary}}'  => $monthlyGross,
                '{{basic_salary}}'          => $basicSalary,
                '{{salary_in_words}}'       => $salaryInWords,
                '{{gross_earnings}}'        => $monthlyGross,
                '{{total_deductions}}'      => $salary > 0 ? ('₹' . number_format(($salary / 12) * 0.1, 2)) : 'N/A',
                '{{net_pay}}'               => $salary > 0 ? ('₹' . number_format(($salary / 12) * 0.9, 2)) : 'N/A',
                '{{net_pay_in_words}}'      => $salary > 0 ? ($this->numberToWords(($salary / 12) * 0.9) . ' Only') : 'N/A',
                '{{payslip_month}}'         => Carbon::today()->format('F Y'),
                '{{pay_period}}'            => Carbon::today()->format('F Y'),
                '{{working_days}}'          => '30',
                '{{paid_days}}'             => '30',
                '{{lop_days}}'              => '0',
                '{{salary_mode}}'           => 'Bank Transfer',
                '{{fnf_net_payable}}'       => $annualCtc,
                '{{fnf_net_payable_words}}' => $salaryInWords,

                // Banking & Statutory
                '{{bank_name}}'             => e($employee->bank_name ?? 'N/A'),
                '{{bank_account_number}}'   => e($employee->account_number ?? $employee->bank_account_number ?? 'N/A'),
                '{{ifsc_code}}'             => e($employee->ifsc_code ?? $employee->bank_ifsc ?? 'N/A'),
                '{{pan_number}}'            => e($employee->pan_card_number ?? $employee->pan_number ?? 'N/A'),
                '{{aadhaar_number}}'        => e($employee->aadhaar_card_number ?? 'N/A'),
                '{{uan_number}}'            => e($employee->uan_number ?? 'N/A'),
                '{{pf_number}}'             => e($employee->pf_number ?? $employee->uan_number ?? 'N/A'),

                '{{company_name}}'          => e($company?->company_name ?? 'Company Name'),
                '{{company_logo}}'          => $company?->logo ? asset('storage/' . $company->logo) : '',
                '{{company_address}}'       => e($company?->address ?? 'Headquarters'),
                '{{company_email}}'         => e($company?->email ?? 'info@company.com'),
                '{{company_phone}}'         => e($company?->phone ?? 'N/A'),
                '{{company_website}}'       => e($company?->website ?? 'N/A'),
                
                '{{current_date}}'          => Carbon::today()->format('d M, Y'),
                '{{issue_date}}'            => $issueDateStr,
                '{{reference_number}}'      => e($refNo),

                '{{hr_signature}}'          => $hrSigHtml,
                '{{hr_name}}'               => e($hrName),
                '{{hr_designation}}'        => e($hrDesignation),
                '{{signature_date}}'        => $issueDateStr,
                '{{employee_signature}}'    => '<div style="display:inline-block; border-bottom:1.5px solid #0f172a; width:180px; height:35px; text-align:center; color:#94a3b8; font-size:11px; line-height:45px;">[ Employee Signature ]</div>',
            ];

            // Render Relational Tables & Complex Blocks
            $dictionary['{{education_table}}'] = $this->renderEducationTable($employee);
            $dictionary['{{experience_table}}'] = $this->renderExperienceTable($employee);
            $dictionary['{{skills_list}}'] = $this->renderSkillsList($employee);
            $dictionary['{{certifications_list}}'] = $this->renderCertificationsList($employee);
            
            // Standard compensation breakdown table
            $monthlyBasic = ($salary / 12) * 0.5;
            $monthlyHra = ($salary / 12) * 0.3;
            $monthlySpecial = ($salary / 12) * 0.2;
            $monthlyGrossVal = $salary / 12;
            $dictionary['{{salary_breakdown_table}}'] = '<table border="1" cellpadding="6" cellspacing="0" style="width:100%; border-collapse:collapse; border:1px solid #cbd5e1; font-size:12px; margin:10px 0;">' .
                '<thead style="background-color:#f8fafc; font-weight:bold;"><tr><th style="text-align:left; padding:6px 8px;">Salary Component</th><th style="text-align:right; width:120px; padding:6px 8px;">Monthly (₹)</th><th style="text-align:right; width:120px; padding:6px 8px;">Annual (₹)</th></tr></thead>' .
                '<tbody>' .
                '<tr><td style="padding:5px 8px;">Basic Salary (50%)</td><td style="text-align:right; padding:5px 8px;">₹' . number_format($monthlyBasic, 2) . '</td><td style="text-align:right; padding:5px 8px;">₹' . number_format($monthlyBasic * 12, 2) . '</td></tr>' .
                '<tr><td style="padding:5px 8px;">House Rent Allowance (HRA 30%)</td><td style="text-align:right; padding:5px 8px;">₹' . number_format($monthlyHra, 2) . '</td><td style="text-align:right; padding:5px 8px;">₹' . number_format($monthlyHra * 12, 2) . '</td></tr>' .
                '<tr><td style="padding:5px 8px;">Special Allowance (20%)</td><td style="text-align:right; padding:5px 8px;">₹' . number_format($monthlySpecial, 2) . '</td><td style="text-align:right; padding:5px 8px;">₹' . number_format($monthlySpecial * 12, 2) . '</td></tr>' .
                '<tr style="font-weight:bold; background-color:#f8fafc;"><td style="padding:6px 8px;">Total Cost to Company (CTC)</td><td style="text-align:right; padding:6px 8px;">₹' . number_format($monthlyGrossVal, 2) . '</td><td style="text-align:right; padding:6px 8px;">₹' . number_format($salary, 2) . '</td></tr>' .
                '</tbody></table>';

            $dictionary['{{earnings_table}}'] = $dictionary['{{salary_breakdown_table}}'];
            $dictionary['{{deductions_table}}'] = '<p class="text-muted fs-12">Standard Statutory Deductions as per IT & PF Rules.</p>';
            $dictionary['{{fnf_settlement_table}}'] = '<p class="text-muted fs-12">Full & Final settlement clearances completed.</p>';

            // Apply substitutions
            foreach ($dictionary as $tag => $val) {
                $fullHtml = str_replace($tag, $val, $fullHtml);
            }
        } else {
            // Preview mode with NO employee selected:
            // Keep all employee placeholders intact (e.g. {{employee_name}}, {{designation}}, {{annual_ctc}}, etc.)
            // Resolve company details if available
            if ($company) {
                $companyDict = [
                    '{{company_name}}'    => e($company->company_name),
                    '{{company_logo}}'    => $company->logo ? asset('storage/' . $company->logo) : '',
                    '{{company_address}}' => e($company->address ?? ''),
                    '{{company_email}}'   => e($company->email ?? ''),
                    '{{company_phone}}'   => e($company->phone ?? ''),
                    '{{company_website}}' => e($company->website ?? ''),
                ];
                foreach ($companyDict as $tag => $val) {
                    if ($val !== '') {
                        $fullHtml = str_replace($tag, $val, $fullHtml);
                    }
                }
            }
        }

        // Wrap with CSS styling container
        $customCss = $template->css_styles ?? '';
        $wrapper = '<div class="generated-doc-container" style="font-family: Arial, Helvetica, sans-serif; color: #1e293b; line-height: 1.5; padding: 30px; background: #ffffff;">';
        $wrapper .= '<style>
            .generated-doc-container { line-height: 1.5; font-family: Arial, Helvetica, sans-serif; color: #1e293b; font-size: 14px; }
            .generated-doc-container p { margin-top: 0; margin-bottom: 0.35em; line-height: 1.5; }
            .generated-doc-container h1, .generated-doc-container h2, .generated-doc-container h3, .generated-doc-container h4 { margin-top: 0.6em; margin-bottom: 0.3em; font-weight: bold; }
            .generated-doc-container table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 13px; }
            .generated-doc-container th, .generated-doc-container td { border: 1px solid #cbd5e1; padding: 6px 10px; vertical-align: top; }
            .generated-doc-container th { background-color: #f8fafc; font-weight: bold; }
            .generated-doc-container td p, .generated-doc-container th p { margin-bottom: 0 !important; }
            .generated-doc-container .ql-align-center { text-align: center; }
            .generated-doc-container .ql-align-right { text-align: right; }
            .generated-doc-container .ql-align-justify { text-align: justify; }
            .generated-doc-container ul, .generated-doc-container ol { margin-top: 0; margin-bottom: 0.5em; padding-left: 24px; }
            .generated-doc-container li { margin-bottom: 0.2em; }
            .generated-doc-container img { max-width: 100%; height: auto; }
        </style>';
        if ($customCss) {
            $wrapper .= '<style>' . $customCss . '</style>';
        }
        $wrapper .= $fullHtml . '</div>';

        return $wrapper;
    }

    /**
     * Wrap a renderTemplate() fragment as a standalone A4 document for PDF/print
     * output (DomPDF's `@page` support, not the on-screen preview/DB fragment).
     */
    public function toPrintableDocument(string $renderedContent, string $title = 'Document'): string
    {
        $safeTitle = e($title);

        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>{$safeTitle}</title>
                <style>
                    @page { size: A4; margin: 15mm; }
                    body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #1e293b; font-size: 11pt; line-height: 1.5; }
                    p { margin-top: 0; margin-bottom: 0.35em; line-height: 1.5; }
                    h1, h2, h3, h4 { margin-top: 0.5em; margin-bottom: 0.3em; font-weight: bold; }
                    .ql-align-center { text-align: center; }
                    .ql-align-right { text-align: right; }
                    .ql-align-justify { text-align: justify; }
                    table { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 10.5pt; }
                    th, td { border: 1px solid #cbd5e1; padding: 6px 8px; vertical-align: top; }
                    th { background-color: #f8fafc; font-weight: bold; }
                    td p, th p { margin-bottom: 0 !important; }
                    ul, ol { margin-top: 0; margin-bottom: 0.5em; padding-left: 20px; }
                    li { margin-bottom: 0.2em; }
                    .generated-doc-container { padding: 0 !important; }
                    img { max-width: 100%; height: auto; }
                </style>
            </head>
            <body>
                {$renderedContent}
            </body>
            </html>
            HTML;
    }

    /**
     * Render complete payslip HTML using a DocumentTemplate (or standard fallback layout).
     */
    public function renderPayslip(?DocumentTemplate $template, array $data): string
    {
        /** @var Employee $employee */
        $employee = $data['employee'];
        $company = $data['company'] ?? $employee->company ?: Company::first();
        $calc = $data['calc'] ?? [];
        $details = $data['details'] ?? [];
        $monthFormatted = $data['payroll_month_formatted'] ?? Carbon::now()->format('F Y');
        $netPayout = $calc['net_payout'] ?? 0;
        $netPayoutInWords = $data['netPayoutInWords'] ?? ($this->numberToWords((float)$netPayout) . ' Only');
        $run = $data['run'] ?? null;
        $refNo = $data['reference_number'] ?? ('PAY/' . ($company?->code ?? 'ORG') . '/' . ($run?->payroll_month ?? date('Y-m')) . '/' . str_pad((string)$employee->id, 4, '0', STR_PAD_LEFT));

        $totalDays = $calc['total_days'] ?? 30;
        $lopDays = $calc['lop_days'] ?? 0;
        $paidDays = max(0, $totalDays - $lopDays);

        // Compute total earnings and deductions from details if not in summary
        $grossEarning = 0;
        $totalDeduction = 0;
        foreach ($details as $item) {
            $val = (float)($item['calculated_value'] ?? 0);
            if (($item['type'] ?? '') === 'earning') {
                $grossEarning += $val;
            } elseif (($item['type'] ?? '') === 'deduction') {
                $totalDeduction += $val;
            }
        }
        $grossEarning += (float)($calc['adhoc_earnings'] ?? 0) + (float)($calc['retro_lop_reversals'] ?? 0);
        $totalDeduction += (float)($calc['adhoc_deductions'] ?? 0);

        // HR Signatory
        $hrName = $data['hr_name'] ?? 'Authorized Signatory';
        $hrDesignation = $data['hr_designation'] ?? 'Finance & Payroll Department';
        $issueDateStr = !empty($data['issue_date']) ? Carbon::parse($data['issue_date'])->format('d M, Y') : Carbon::today()->format('d M, Y');

        $dictionary = [
            '{{employee_name}}'         => e($employee->full_name ?? ''),
            '{{employee_id}}'           => e($employee->employee_id ?? ''),
            '{{designation}}'           => e($employee->designation?->name ?? $employee->job_title ?? 'Employee'),
            '{{department}}'            => e($employee->department?->name ?? 'General'),
            '{{branch}}'                => e($employee->branch?->name ?? 'Main Branch'),
            '{{joining_date}}'          => $employee->date_of_joining ? Carbon::parse($employee->date_of_joining)->format('d M, Y') : 'N/A',
            
            '{{bank_name}}'             => e($employee->bank_name ?? 'N/A'),
            '{{bank_account_number}}'   => e($employee->account_number ?? $employee->bank_account_number ?? 'N/A'),
            '{{ifsc_code}}'             => e($employee->ifsc_code ?? $employee->bank_ifsc ?? 'N/A'),
            '{{pan_number}}'            => e($employee->pan_number ?? 'N/A'),
            '{{uan_number}}'            => e($employee->uan_number ?? 'N/A'),
            '{{pf_number}}'             => e($employee->pf_number ?? $employee->uan_number ?? 'N/A'),

            '{{payslip_month}}'         => e($monthFormatted),
            '{{pay_period}}'            => e($monthFormatted),
            '{{working_days}}'          => e((string)$totalDays),
            '{{paid_days}}'             => e((string)$paidDays),
            '{{lop_days}}'              => e((string)$lopDays),
            '{{salary_mode}}'           => e($data['salary_mode'] ?? 'Bank Transfer'),

            '{{gross_earnings}}'        => '₹' . number_format($grossEarning, 2),
            '{{total_deductions}}'      => '₹' . number_format($totalDeduction, 2),
            '{{net_pay}}'               => '₹' . number_format((float)$netPayout, 2),
            '{{net_pay_in_words}}'      => e($netPayoutInWords),

            '{{company_name}}'          => e($company?->company_name ?? 'Company Name'),
            '{{company_logo}}'          => $company?->logo ? asset('storage/' . $company->logo) : '',
            '{{company_address}}'       => e($company?->address ?? 'Corporate Head Office'),
            '{{company_email}}'         => e($company?->email ?? 'finance@company.com'),
            '{{company_phone}}'         => e($company?->phone ?? 'N/A'),

            '{{issue_date}}'            => $issueDateStr,
            '{{current_date}}'          => Carbon::today()->format('d M, Y'),
            '{{reference_number}}'      => e($refNo),
            '{{hr_name}}'               => e($hrName),
            '{{hr_designation}}'        => e($hrDesignation),
            '{{hr_signature}}'          => '<div style="display:inline-block; border-bottom:1px solid #475569; width:160px; height:28px;"></div>',
        ];

        // Dynamic Tables
        $dictionary['{{earnings_table}}'] = $this->renderEarningsTable($details, $calc);
        $dictionary['{{deductions_table}}'] = $this->renderDeductionsTable($details, $calc);
        $dictionary['{{salary_breakdown_table}}'] = $this->renderSalaryBreakdownTable($details, $calc);

        if ($template) {
            $header = $template->header_content ?? '';
            $body = $template->body_content ?? '';
            $footer = $template->footer_content ?? '';
            $customCss = $template->css_styles ?? '';

            $fullHtml = '';
            if ($header) {
                $fullHtml .= '<header class="doc-header mb-3">' . $header . '</header>';
            }
            $fullHtml .= '<main class="doc-body">' . $body . '</main>';
            if ($footer) {
                $fullHtml .= '<footer class="doc-footer mt-4 pt-3 border-top">' . $footer . '</footer>';
            }

            foreach ($dictionary as $tag => $val) {
                $fullHtml = str_replace($tag, $val, $fullHtml);
            }

            $wrapper = '<div class="generated-doc-container payslip-doc" style="font-family: Arial, Helvetica, sans-serif; color: #1e293b; line-height: 1.5; padding: 20px; background: #ffffff;">';
            if ($customCss) {
                $wrapper .= '<style>' . $customCss . '</style>';
            }
            $wrapper .= $fullHtml . '</div>';

            return $wrapper;
        }

        // Standard Default Payslip HTML Layout if no template object passed
        return $this->buildDefaultPayslipHtml($dictionary);
    }

    /**
     * Render complete exit document HTML using a DocumentTemplate (or standard fallback layout).
     */
    public function renderExitDocument(?DocumentTemplate $template, EmployeeExit $exit, string $docType, array $extraData = []): string
    {
        $exit->loadMissing(['employee.company', 'employee.department', 'employee.designation', 'employee.branch', 'fnfSettlement']);
        $employee = $exit->employee;
        $company = $employee?->company ?: Company::first();
        $settlement = $exit->fnfSettlement;

        $lwd = $exit->approved_lwd ?: ($exit->preferred_lwd ?: Carbon::today());
        $lwdCarbon = Carbon::parse($lwd);
        $doj = $employee?->date_of_joining ? Carbon::parse($employee->date_of_joining) : Carbon::today();
        
        $diff = $doj->diff($lwdCarbon);
        $tenureString = trim(($diff->y ? $diff->y . ' year' . ($diff->y > 1 ? 's ' : ' ') : '') . ($diff->m ? $diff->m . ' month' . ($diff->m > 1 ? 's' : '') : ''));
        if (empty($tenureString)) {
            $tenureString = $diff->days . ' days';
        }

        $refPrefix = match($docType) {
            'experience_certificate' => 'EXP',
            'noc_certificate' => 'NOC',
            'fnf_statement' => 'FNF',
            default => 'REL',
        };
        $refNo = $extraData['reference_number'] ?? ($refPrefix . '/' . ($company?->code ?? 'ORG') . '/' . date('Y') . '/' . str_pad((string)$exit->id, 4, '0', STR_PAD_LEFT));

        $hrName = $extraData['hr_name'] ?? auth()->user()?->name ?? 'Head of Human Resources';
        $hrDesignation = $extraData['hr_designation'] ?? 'Authorized Signatory';
        $issueDateStr = !empty($extraData['issue_date']) ? Carbon::parse($extraData['issue_date'])->format('d M, Y') : Carbon::today()->format('d M, Y');

        $fnfNetPayable = $settlement?->net_payable_amount ?? 0;
        $fnfNetPayableWords = $this->numberToWords((float)$fnfNetPayable) . ' Only';

        $dictionary = [
            '{{employee_name}}'         => e($employee?->full_name ?? ''),
            '{{employee_id}}'           => e($employee?->employee_id ?? ''),
            '{{designation}}'           => e($employee?->designation?->name ?? 'Employee'),
            '{{department}}'            => e($employee?->department?->name ?? 'N/A'),
            '{{branch}}'                => e($employee?->branch?->name ?? 'Main Office'),
            '{{joining_date}}'          => $doj->format('d M, Y'),
            '{{date_of_joining}}'       => $doj->format('d M, Y'),
            '{{last_working_day}}'      => $lwdCarbon->format('d M, Y'),
            '{{relieving_date}}'        => $lwdCarbon->format('d M, Y'),
            '{{resignation_date}}'      => $exit->resignation_date ? Carbon::parse($exit->resignation_date)->format('d M, Y') : 'N/A',
            '{{separation_type}}'       => e(ucfirst(str_replace('_', ' ', $exit->separation_type ?? 'resignation'))),
            '{{tenure_string}}'         => e($tenureString),
            '{{conduct_statement}}'     => 'During their tenure, we found their character, dedication, and professional conduct to be exemplary.',
            '{{clearance_status}}'      => 'All company assets, dues, files, and accounts have been fully cleared across IT, Finance, HR, Admin, and Operations departments.',

            '{{fnf_net_payable}}'       => '₹' . number_format((float)$fnfNetPayable, 2),
            '{{fnf_net_payable_words}}' => e($fnfNetPayableWords),
            '{{fnf_earnings_total}}'    => '₹' . number_format((float)($settlement?->total_earnings ?? 0), 2),
            '{{fnf_deductions_total}}'  => '₹' . number_format((float)($settlement?->total_deductions ?? 0), 2),
            '{{gratuity_amount}}'       => '₹' . number_format((float)($settlement?->gratuity_amount ?? 0), 2),
            '{{leave_encashment_amount}}'=> '₹' . number_format((float)($settlement?->leave_encashment_amount ?? 0), 2),
            '{{notice_pay_amount}}'     => '₹' . number_format((float)($settlement?->notice_shortfall_recovery ?? 0), 2),

            '{{company_name}}'          => e($company?->company_name ?? 'Company Name'),
            '{{company_logo}}'          => $company?->logo ? asset('storage/' . $company->logo) : '',
            '{{company_address}}'       => e($company?->address ?? 'Corporate Headquarters'),
            '{{company_email}}'         => e($company?->email ?? 'hr@company.com'),
            '{{company_phone}}'         => e($company?->phone ?? 'N/A'),

            '{{issue_date}}'            => $issueDateStr,
            '{{current_date}}'          => Carbon::today()->format('d M, Y'),
            '{{reference_number}}'      => e($refNo),
            '{{hr_name}}'               => e($hrName),
            '{{hr_designation}}'        => e($hrDesignation),
            '{{hr_signature}}'          => '<div style="display:inline-block; border-bottom:1.5px solid #0f172a; width:180px; height:35px; text-align:center; color:#94a3b8; font-size:11px; line-height:45px;">[ Authorized Signatory ]</div>',
        ];

        // Dynamic Tables
        if ($settlement) {
            $dictionary['{{fnf_settlement_table}}'] = $this->renderFnfSettlementTable($settlement);
        } else {
            $dictionary['{{fnf_settlement_table}}'] = '<p class="text-muted fs-12">No settlement breakdown on record.</p>';
        }

        if ($template) {
            $header = $template->header_content ?? '';
            $body = $template->body_content ?? '';
            $footer = $template->footer_content ?? '';
            $customCss = $template->css_styles ?? '';

            $fullHtml = '';
            if ($header) {
                $fullHtml .= '<header class="doc-header mb-3">' . $header . '</header>';
            }
            $fullHtml .= '<main class="doc-body">' . $body . '</main>';
            if ($footer) {
                $fullHtml .= '<footer class="doc-footer mt-4 pt-3 border-top">' . $footer . '</footer>';
            }

            foreach ($dictionary as $tag => $val) {
                $fullHtml = str_replace($tag, $val, $fullHtml);
            }

            $wrapper = '<div class="generated-doc-container exit-doc" style="font-family: Arial, Helvetica, sans-serif; color: #1e293b; line-height: 1.65; padding: 30px; background: #ffffff;">';
            if ($customCss) {
                $wrapper .= '<style>' . $customCss . '</style>';
            }
            $wrapper .= $fullHtml . '</div>';

            return $wrapper;
        }

        return '';
    }

    /**
     * Render tabular earnings list.
     */
    public function renderEarningsTable(array $details, array $calc): string
    {
        $html = '<table border="1" cellpadding="6" cellspacing="0" style="width:100%; border-collapse:collapse; border:1px solid #cbd5e1; font-size:11px;">';
        $html .= '<thead style="background-color:#f1f5f9; color:#334155;"><tr><th style="text-align:left; padding:6px 8px;">Earnings Component</th><th style="text-align:right; width:90px; padding:6px 8px;">Amount</th></tr></thead><tbody>';

        $gross = 0;
        foreach ($details as $item) {
            if (($item['type'] ?? '') === 'earning') {
                $val = (float)($item['calculated_value'] ?? 0);
                $gross += $val;
                $html .= '<tr><td style="padding:5px 8px;">' . e($item['name']) . '</td><td style="text-align:right; padding:5px 8px;">₹' . number_format($val, 2) . '</td></tr>';
            }
        }

        if (!empty($calc['adhoc_earnings']) && $calc['adhoc_earnings'] > 0) {
            $gross += (float)$calc['adhoc_earnings'];
            $html .= '<tr><td style="padding:5px 8px;">Ad-hoc Earnings / Bonus</td><td style="text-align:right; padding:5px 8px;">₹' . number_format($calc['adhoc_earnings'], 2) . '</td></tr>';
        }

        if (!empty($calc['retro_lop_reversals']) && $calc['retro_lop_reversals'] > 0) {
            $gross += (float)$calc['retro_lop_reversals'];
            $html .= '<tr><td style="padding:5px 8px;">Retroactive LOP Refunds</td><td style="text-align:right; padding:5px 8px;">₹' . number_format($calc['retro_lop_reversals'], 2) . '</td></tr>';
        }

        $html .= '<tr style="font-weight:bold; background-color:#f8fafc;"><td style="padding:6px 8px;">Gross Earnings</td><td style="text-align:right; padding:6px 8px;">₹' . number_format($gross, 2) . '</td></tr>';
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Render tabular deductions list.
     */
    public function renderDeductionsTable(array $details, array $calc): string
    {
        $html = '<table border="1" cellpadding="6" cellspacing="0" style="width:100%; border-collapse:collapse; border:1px solid #cbd5e1; font-size:11px;">';
        $html .= '<thead style="background-color:#f1f5f9; color:#334155;"><tr><th style="text-align:left; padding:6px 8px;">Deductions Component</th><th style="text-align:right; width:90px; padding:6px 8px;">Amount</th></tr></thead><tbody>';

        $total = 0;
        foreach ($details as $item) {
            if (($item['type'] ?? '') === 'deduction') {
                $val = (float)($item['calculated_value'] ?? 0);
                $total += $val;
                $html .= '<tr><td style="padding:5px 8px;">' . e($item['name']) . '</td><td style="text-align:right; padding:5px 8px;">₹' . number_format($val, 2) . '</td></tr>';
            }
        }

        if (!empty($calc['adhoc_deductions']) && $calc['adhoc_deductions'] > 0) {
            $total += (float)$calc['adhoc_deductions'];
            $html .= '<tr><td style="padding:5px 8px;">Ad-hoc Deductions</td><td style="text-align:right; padding:5px 8px;">₹' . number_format($calc['adhoc_deductions'], 2) . '</td></tr>';
        }

        $html .= '<tr style="font-weight:bold; background-color:#f8fafc;"><td style="padding:6px 8px;">Total Deductions</td><td style="text-align:right; padding:6px 8px;">₹' . number_format($total, 2) . '</td></tr>';
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Render two-column side-by-side salary breakdown table.
     */
    public function renderSalaryBreakdownTable(array $details, array $calc): string
    {
        $earningsHtml = $this->renderEarningsTable($details, $calc);
        $deductionsHtml = $this->renderDeductionsTable($details, $calc);

        return '<table style="width:100%; border-collapse:collapse; margin:10px 0;"><tr><td style="width:48%; vertical-align:top; padding-right:10px;">' . $earningsHtml . '</td><td style="width:48%; vertical-align:top; padding-left:10px;">' . $deductionsHtml . '</td></tr></table>';
    }

    /**
     * Render full and final settlement tabular statement.
     */
    public function renderFnfSettlementTable(EmployeeFnfSettlement $settlement): string
    {
        $html = '<table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:collapse; border:1px solid #cbd5e1; font-size:12px; margin:12px 0;">';
        $html .= '<thead style="background-color:#f1f5f9; color:#1e293b;"><tr><th style="text-align:left; width:50%;">Earnings / Payable Items</th><th style="text-align:right; width:20%;">Amount</th><th style="text-align:left; width:50%;">Recoveries / Deductions</th><th style="text-align:right; width:20%;">Amount</th></tr></thead><tbody>';

        $html .= '<tr>';
        $html .= '<td>Unpaid Salary (' . ($settlement->unpaid_salary_days ?? 0) . ' days)</td>';
        $html .= '<td style="text-align:right;">₹' . number_format((float)($settlement->unpaid_salary_amount ?? 0), 2) . '</td>';
        $html .= '<td>Notice Period Shortfall</td>';
        $html .= '<td style="text-align:right;">₹' . number_format((float)($settlement->notice_shortfall_recovery ?? 0), 2) . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td>Leave Encashment (' . ($settlement->leave_encashment_days ?? 0) . ' days)</td>';
        $html .= '<td style="text-align:right;">₹' . number_format((float)($settlement->leave_encashment_amount ?? 0), 2) . '</td>';
        $html .= '<td>Unsettled Advances / Loans</td>';
        $html .= '<td style="text-align:right;">₹' . number_format((float)($settlement->unsettled_advances_recovery ?? 0), 2) . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td>Gratuity Amount</td>';
        $html .= '<td style="text-align:right;">₹' . number_format((float)($settlement->gratuity_amount ?? 0), 2) . '</td>';
        $html .= '<td>Asset Damage / Non-Return</td>';
        $html .= '<td style="text-align:right;">₹' . number_format((float)($settlement->asset_damage_recovery ?? 0), 2) . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td>Performance Bonus / Other</td>';
        $html .= '<td style="text-align:right;">₹' . number_format((float)(($settlement->bonus_amount ?? 0) + ($settlement->other_earnings ?? 0)), 2) . '</td>';
        $html .= '<td>Other Deductions / Tax</td>';
        $html .= '<td style="text-align:right;">₹' . number_format((float)($settlement->other_deductions ?? 0), 2) . '</td>';
        $html .= '</tr>';

        $html .= '<tr style="font-weight:bold; background-color:#f8fafc;">';
        $html .= '<td>Total Payable Earnings (A)</td>';
        $html .= '<td style="text-align:right; color:#16a34a;">₹' . number_format((float)($settlement->total_earnings ?? 0), 2) . '</td>';
        $html .= '<td>Total Deductions (B)</td>';
        $html .= '<td style="text-align:right; color:#dc2626;">₹' . number_format((float)($settlement->total_deductions ?? 0), 2) . '</td>';
        $html .= '</tr>';

        $html .= '<tr style="font-weight:bold; background-color:#ecfdf5; font-size:13px;">';
        $html .= '<td colspan="3" style="text-align:right; color:#065f46;">Net Payable Settlement Amount (A - B):</td>';
        $html .= '<td style="text-align:right; color:#059669;">₹' . number_format((float)($settlement->net_payable_amount ?? 0), 2) . '</td>';
        $html .= '</tr>';

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Fallback standard payslip HTML structure.
     */
    private function buildDefaultPayslipHtml(array $dict): string
    {
        return <<<HTML
        <div style="font-family: Arial, Helvetica, sans-serif; color: #1e293b; line-height: 1.4; padding: 20px;">
            <div style="border-bottom: 2px solid #1c3faa; padding-bottom: 10px; margin-bottom: 16px;">
                <table style="width: 100%;">
                    <tr>
                        <td style="vertical-align: top;">
                            <div style="font-size: 16px; font-weight: bold; color: #1c3faa; text-transform: uppercase;">{$dict['{{company_name}}']}</div>
                            <div style="font-size: 10px; color: #64748b; margin-top: 3px;">
                                {$dict['{{company_address}}']}<br>
                                Email: {$dict['{{company_email}}']} | Tel: {$dict['{{company_phone}}']}
                            </div>
                        </td>
                        <td style="vertical-align: top; text-align: right;">
                            <div style="font-size: 13px; font-weight: bold; color: #1e293b; text-transform: uppercase;">SALARY SLIP</div>
                            <div style="font-size: 10px; color: #64748b;">Month: {$dict['{{payslip_month}}']}</div>
                            <div style="font-size: 9px; color: #94a3b8;">Ref: {$dict['{{reference_number}}']}</div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Employee Meta Grid -->
            <table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse; border: 1px solid #cbd5e1; font-size: 10px; margin-bottom: 16px;">
                <tr style="background-color: #f8fafc;">
                    <td style="width: 25%;"><strong>Employee Name:</strong><br>{$dict['{{employee_name}}']}</td>
                    <td style="width: 25%;"><strong>Employee ID:</strong><br>{$dict['{{employee_id}}']}</td>
                    <td style="width: 25%;"><strong>Designation:</strong><br>{$dict['{{designation}}']}</td>
                    <td style="width: 25%;"><strong>Department:</strong><br>{$dict['{{department}}']}</td>
                </tr>
                <tr>
                    <td><strong>Bank Name:</strong><br>{$dict['{{bank_name}}']}</td>
                    <td><strong>Account Number:</strong><br>{$dict['{{bank_account_number}}']}</td>
                    <td><strong>PAN Number:</strong><br>{$dict['{{pan_number}}']}</td>
                    <td><strong>UAN / PF:</strong><br>{$dict['{{uan_number}}']}</td>
                </tr>
                <tr style="background-color: #f8fafc;">
                    <td><strong>Cycle Days:</strong><br>{$dict['{{working_days}}']} Days</td>
                    <td><strong>Paid Days:</strong><br>{$dict['{{paid_days}}']} Days</td>
                    <td><strong>LOP Days:</strong><br>{$dict['{{lop_days}}']} Days</td>
                    <td><strong>Payment Mode:</strong><br>{$dict['{{salary_mode}}']}</td>
                </tr>
            </table>

            <!-- Dynamic Tables -->
            {$dict['{{salary_breakdown_table}}']}

            <!-- Net Pay Box -->
            <div style="background-color: #f0fdf6; border: 1px solid #bbf7d0; border-radius: 4px; padding: 10px 14px; margin-top: 14px;">
                <table style="width: 100%;">
                    <tr>
                        <td style="font-size: 11px; font-weight: bold; color: #166534;">NET SALARY PAYOUT:</td>
                        <td style="font-size: 15px; font-weight: bold; color: #16a34a; text-align: right;">{$dict['{{net_pay}}']}</td>
                    </tr>
                </table>
                <div style="font-size: 10px; font-style: italic; color: #475569; margin-top: 4px;">
                    <strong>In Words:</strong> {$dict['{{net_pay_in_words}}']}
                </div>
            </div>

            <!-- Signatures -->
            <div style="margin-top: 35px; width: 100%;">
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 50%; text-align: center;">
                            <div style="width: 140px; border-bottom: 1px solid #64748b; margin: 0 auto 4px auto;"></div>
                            <span style="font-size: 9px; font-weight: bold; color: #64748b;">Employee Signature</span>
                        </td>
                        <td style="width: 50%; text-align: center;">
                            <div style="width: 140px; border-bottom: 1px solid #64748b; margin: 0 auto 4px auto;"></div>
                            <span style="font-size: 9px; font-weight: bold; color: #64748b;">Authorized Signatory</span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Footer -->
            <div style="margin-top: 25px; border-top: 1px dashed #cbd5e1; padding-top: 8px; text-align: center; font-size: 8.5px; color: #94a3b8;">
                This is a computer-generated document issued by {$dict['{{company_name}}']} and does not require a physical signature or seal.
            </div>
        </div>
        HTML;
    }

    /**
     * Convert numeric amount to words in Indian Rupee format.
     */
    public function numberToWords(float|int $number): string
    {
        $no = (int)floor($number);
        $point = (int)round(($number - $no) * 100);
        $hundred = null;
        $digits_1 = strlen((string)$no);
        $i = 0;
        $str = [];
        $words = [
            0 => '', 1 => 'One', 2 => 'Two',
            3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight',
            9 => 'Nine', 10 => 'Ten', 11 => 'Eleven',
            12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
            15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen',
            18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
            30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty',
            60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty',
            90 => 'Ninety',
        ];
        $digits = ['', 'Hundred', 'Thousand', 'Lakh', 'Crore'];

        while ($i < $digits_1) {
            $divider = ($i === 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += ($divider === 10) ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter === 1 && $str[0]) ? ' and ' : null;
                $str[] = ($number < 21) ? $words[$number] . ' ' . $digits[$counter] . $plural . ' ' . $hundred
                    : $words[floor($number / 10) * 10] . ' ' . $words[$number % 10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
            } else {
                $str[] = null;
            }
        }

        $str = array_reverse($str);
        $result = implode('', $str);
        $points = ($point) ? (' and ' . $words[floor($point / 10) * 10] . ' ' . $words[$point % 10] . ' Paise') : '';

        return trim($result) ? trim($result) . ' Rupees' . $points : 'Zero Rupees';
    }

    private function renderEducationTable(Employee $employee): string
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('employee_education_histories')) {
                return '<p class="text-muted fs-12">No educational qualification records on file.</p>';
            }

            $educations = \DB::table('employee_education_histories')
                ->where('employee_id', $employee->id)
                ->get();

            if ($educations->isEmpty()) {
                return '<p class="text-muted fs-12">No educational qualification records on file.</p>';
            }

            $html = '<table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse; border: 1px solid #cbd5e1; margin: 10px 0; font-size: 13px;">';
            $html .= '<thead style="background-color: #f8fafc; font-weight: bold; text-align: left;">';
            $html .= '<tr><th>Degree / Qualification</th><th>Institution / University</th><th>Passing Year</th><th>Grade / Percentage</th></tr>';
            $html .= '</thead><tbody>';

            foreach ($educations as $edu) {
                $html .= '<tr>';
                $html .= '<td>' . e($edu->degree ?? $edu->qualification ?? 'Degree') . '</td>';
                $html .= '<td>' . e($edu->institution ?? $edu->university ?? 'University') . '</td>';
                $html .= '<td>' . e($edu->passing_year ?? 'N/A') . '</td>';
                $html .= '<td>' . e($edu->grade ?? $edu->percentage ?? 'N/A') . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            return $html;
        } catch (\Throwable $e) {
            return '<p class="text-muted fs-12">No educational qualification records on file.</p>';
        }
    }

    private function renderExperienceTable(Employee $employee): string
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('employee_employment_histories')) {
                return '<p class="text-muted fs-12">No past employment history records on file.</p>';
            }

            $experiences = \DB::table('employee_employment_histories')
                ->where('employee_id', $employee->id)
                ->get();

            if ($experiences->isEmpty()) {
                return '<p class="text-muted fs-12">No past employment history records on file.</p>';
            }

            $html = '<table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse; border: 1px solid #cbd5e1; margin: 10px 0; font-size: 13px;">';
            $html .= '<thead style="background-color: #f8fafc; font-weight: bold; text-align: left;">';
            $html .= '<tr><th>Company / Organization</th><th>Designation / Role</th><th>From</th><th>To</th></tr>';
            $html .= '</thead><tbody>';

            foreach ($experiences as $exp) {
                $html .= '<tr>';
                $html .= '<td>' . e($exp->company_name ?? 'Company') . '</td>';
                $html .= '<td>' . e($exp->designation ?? 'Role') . '</td>';
                $html .= '<td>' . e(isset($exp->start_date) && $exp->start_date ? Carbon::parse($exp->start_date)->format('M Y') : 'N/A') . '</td>';
                $html .= '<td>' . e(isset($exp->end_date) && $exp->end_date ? Carbon::parse($exp->end_date)->format('M Y') : 'Present') . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
            return $html;
        } catch (\Throwable $e) {
            return '<p class="text-muted fs-12">No past employment history records on file.</p>';
        }
    }

    private function renderSkillsList(Employee $employee): string
    {
        $skills = $employee->skill_set ?? $employee->skills ?? [];
        if (is_string($skills)) {
            $skills = json_decode($skills, true) ?: array_filter(array_map('trim', explode(',', $skills)));
        }

        if (empty($skills)) {
            return '<p class="text-muted fs-12">N/A</p>';
        }

        $items = array_map(fn($s) => '<span style="display: inline-block; background: #e2e8f0; color: #334155; padding: 4px 10px; border-radius: 12px; font-size: 12px; margin-right: 6px; margin-bottom: 6px; font-weight: 600;">' . e(trim($s)) . '</span>', (array)$skills);
        return '<div>' . implode('', $items) . '</div>';
    }

    private function renderCertificationsList(Employee $employee): string
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('employee_certifications')) {
                return '<p class="text-muted fs-12">N/A</p>';
            }

            $certs = \DB::table('employee_certifications')
                ->where('employee_id', $employee->id)
                ->get();

            if ($certs->isEmpty()) {
                return '<p class="text-muted fs-12">N/A</p>';
            }

            $html = '<ul style="padding-left: 20px; font-size: 13px;">';
            foreach ($certs as $c) {
                $html .= '<li><strong>' . e($c->name ?? 'Certification') . '</strong> - Issued by ' . e($c->issued_by ?? 'N/A') . ' (' . e($c->year ?? 'N/A') . ')</li>';
            }
            $html .= '</ul>';
            return $html;
        } catch (\Throwable $e) {
            return '<p class="text-muted fs-12">N/A</p>';
        }
    }
}

