<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Analysis</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        .wrap-content {
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .data-content {
            max-width: 100%;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 0.875rem;
        }

        .json-content {
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 0.375rem;
            margin: 0.25rem 0;
        }

        .collapse-section {
            border: 1px solid #e5e7eb;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .collapse-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .collapse-header {
            background-color: #f3f4f6;
            padding: 1rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s ease;
        }

        .collapse-header:hover {
            background-color: #e5e7eb;
        }

        .collapse-content {
            padding: 1rem;
            background-color: white;
            display: none;
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .collapse-content.show {
            opacity: 1;
            transform: translateY(0);
        }

        .arrow-icon {
            transition: transform 0.3s ease;
        }

        .arrow-icon.active {
            transform: rotate(180deg);
        }

        .nested-content {
            margin-left: 1.5rem;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }

        .header-gradient {
            background: linear-gradient(135deg, #4F46E5 0%, #2563EB 100%);
        }

        .back-button {
            transition: all 0.3s ease;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .download-btn {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            transition: all 0.3s ease;
        }

        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.1), 0 2px 4px -1px rgba(16, 185, 129, 0.06);
        }
    </style>
</head>

<body class="bg-gray-50">
    <header class="header-gradient text-white py-6 shadow-lg">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <h1 class="text-3xl font-bold fade-in">Gambling Analysis</h1>
            <!-- Add Download PDF button in header -->
            <button onclick="generatePDF()"
                class="download-btn text-white px-4 py-2 rounded-lg shadow-md flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Download PDF
            </button>
        </div>
    </header>

    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-semibold mb-6 text-gray-800 fade-in">Analysis Details</h1>
        <div class="bg-white shadow-lg rounded-xl p-6 fade-in">
            @if (!empty($gamblingResult['analysis']))
                @foreach ($gamblingResult['analysis'] as $key => $value)
                    @if (is_array($value))
                        <div class="collapse-section">
                            <div class="collapse-header" onclick="toggleSection(this)">
                                <h2 class="text-lg font-semibold text-gray-800">
                                    {{ ucfirst(str_replace('_', ' ', $key)) }}
                                </h2>
                                <svg class="arrow-icon w-6 h-6 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                            <div class="collapse-content">
                                @if (isset($descriptions[$key]))
                                    <p class="text-sm text-gray-600 italic mb-4">{{ $descriptions[$key] }}</p>
                                @endif

                                @foreach ($value as $subKey => $subValue)
                                    <div class="mb-6">
                                        <div class="collapse-section nested-content">
                                            <div class="collapse-header" onclick="toggleSection(this)">
                                                <h3 class="text-md font-medium text-gray-700">
                                                    {{ ucfirst(str_replace('_', ' ', $subKey)) }}
                                                </h3>
                                                <svg class="arrow-icon w-5 h-5 text-gray-500" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                            <div class="collapse-content">
                                                @if (isset($descriptions[$key][$subKey]))
                                                    <p class="text-sm text-gray-500 italic mb-2">
                                                        {{ $descriptions[$key][$subKey] }}
                                                    </p>
                                                @endif

                                                @if (is_array($subValue))
                                                    <div class="bg-gray-50 rounded-lg p-4">
                                                        <ul class="space-y-3">
                                                            @foreach ($subValue as $item)
                                                                <li class="wrap-content">
                                                                    @if (is_array($item))
                                                                        <div class="data-content json-content">
                                                                            {{ json_encode($item, JSON_PRETTY_PRINT) }}
                                                                        </div>
                                                                    @else
                                                                        <div class="data-content text-gray-600">
                                                                            {{ $item }}
                                                                        </div>
                                                                    @endif
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @else
                                                    <div class="wrap-content text-gray-600">
                                                        {{ $subValue }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            @else
                <p class="text-gray-500 italic">No details available.</p>
            @endif
        </div>
    </div>

    <div class="container mx-auto p-4 text-center">
        <button onclick="goBack()"
            class="back-button bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-3 rounded-lg shadow-md hover:from-blue-700 hover:to-blue-800 font-medium">
            Back to Previous Page
        </button>
    </div>

    <script>
        function generatePDF() {
            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF();

            // Set initial position
            let yPos = 20;

            // Add title
            doc.setFontSize(20);
            doc.setTextColor(0, 51, 102);
            doc.text('Gambling Analysis Report', 20, yPos);
            yPos += 15;

            // Get all the sections
            const sections = document.querySelectorAll('.collapse-section');

            sections.forEach(section => {
                // Get section title
                const title = section.querySelector('.collapse-header h2')?.textContent ||
                    section.querySelector('.collapse-header h3')?.textContent;

                if (title) {
                    // Add section title
                    doc.setFontSize(14);
                    doc.setTextColor(51, 51, 51);

                    // Check if new page is needed
                    if (yPos > 270) {
                        doc.addPage();
                        yPos = 20;
                    }

                    doc.text(title.trim(), 20, yPos);
                    yPos += 10;

                    // Get content
                    const content = section.querySelector('.collapse-content');
                    if (content) {
                        const contentText = content.textContent.trim();
                        doc.setFontSize(12);
                        doc.setTextColor(85, 85, 85);

                        // Split text into lines
                        const lines = doc.splitTextToSize(contentText, 170);
                        lines.forEach(line => {
                            if (yPos > 270) {
                                doc.addPage();
                                yPos = 20;
                            }
                            doc.text(line, 20, yPos);
                            yPos += 7;
                        });

                        yPos += 5;
                    }
                }
            });

            // Add timestamp
            const timestamp = new Date().toLocaleString();
            doc.setFontSize(10);
            doc.setTextColor(128, 128, 128);
            doc.text(`Generated on: ${timestamp}`, 20, 280);

            // Save the PDF
            doc.save('security-analysis.pdf');
        }

        function toggleSection(element) {
            const content = element.nextElementSibling;
            const arrow = element.querySelector('.arrow-icon');

            if (content.style.display === 'block') {
                content.classList.remove('show');
                arrow.classList.remove('active');
                setTimeout(() => {
                    content.style.display = 'none';
                }, 300);
            } else {
                content.style.display = 'block';
                setTimeout(() => {
                    content.classList.add('show');
                }, 10);
                arrow.classList.add('active');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to sections
            const sections = document.querySelectorAll('.collapse-section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                setTimeout(() => {
                    section.style.opacity = '1';
                    section.classList.add('fade-in');
                }, index * 100);
            });

            // Open first section
            const firstSection = document.querySelector('.collapse-section > .collapse-header');
            if (firstSection) {
                setTimeout(() => {
                    firstSection.click();
                }, 500);
            }
        });

        function goBack() {
            gsap.to(document.body, {
                opacity: 0,
                duration: 0.3,
                onComplete: () => {
                    window.history.back();
                }
            });
        }
    </script>
</body>

</html>
