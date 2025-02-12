<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisis Backdoor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .is-transitioning {
            pointer-events: none;
            user-select: none;
        }

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
            background: #116b92;
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
            <h1 class="text-3xl font-bold fade-in">Analisis Backdoor</h1>
            <button onclick="generatePDF()" class="download-btn text-white px-6 py-3 rounded-lg shadow-md font-medium">
                Download PDF
            </button>
        </div>
    </header>

    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-semibold mb-6 text-gray-800 fade-in">Detail Analisis</h1>
        <div class="bg-white shadow-lg rounded-xl p-6 fade-in">
            @if (!empty($backdoorResult['details']))
                @foreach ($backdoorResult['details'] as $key => $value)
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
            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors duration-200 animate-pulse-slow">
            Back to Previous Page
        </button>
    </div>


    <script>
        function generatePDF() {
            getBase64Image("/images/logo.png", function(logoBase64) {
                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF();
                const pageWidth = doc.internal.pageSize.width;
                const pageHeight = doc.internal.pageSize.height;
                const margin = 10;

                // Header background with gradient effect
                const headerHeight = 60;
                doc.setFillColor(41, 58, 74); // Dark blue base color
                doc.rect(0, 0, pageWidth, headerHeight, 'F');


                doc.save("Laporan-Analisis-Backdoor.pdf");
                Swal.fire({
                    icon: 'success',
                    title: 'Download Berhasil!',
                    text: 'Laporan PDF telah berhasil diunduh.',
                    confirmButtonColor: '#2563eb',
                    timer: 3000,
                    timerProgressBar: true
                });
                // Add logo with proper spacing
                const logoSize = 40;
                const logoMargin = 15;
                doc.setFillColor(255, 255, 255);
                doc.roundedRect(logoMargin, 10, logoSize, logoSize, 3, 3, 'F');
                doc.addImage(logoBase64, 'PNG', logoMargin + 2, 12, logoSize - 4, logoSize - 4);

                // Title with better positioning to avoid overlapping with logo
                doc.setFont("helvetica", "bold");
                doc.setFontSize(24);
                doc.setTextColor(255, 255, 255);
                const titleX = logoMargin + logoSize + 20; // Adjust title position to the right of the logo
                doc.text("LAPORAN ANALISIS BACKDOOR", titleX, 30, {
                    align: "left"
                });

                // Subtitle
                doc.setFontSize(14);
                doc.setFont("helvetica", "normal");
                doc.text("Evaluasi Sistem Komprehensif", titleX, 40, {
                    align: "left"
                });

                // Decorative lines
                doc.setDrawColor(255, 255, 255);
                doc.setLineWidth(0.5);
                doc.line(titleX, 15, pageWidth - margin, 15); // Top decorative line
                doc.line(titleX, 45, pageWidth - margin, 45); // Bottom decorative line

                // Report metadata box
                const metadataY = headerHeight + 10;
                doc.setFillColor(245, 247, 250);
                doc.roundedRect(pageWidth - 80, metadataY, 70, 25, 2, 2, 'F');

                doc.setFontSize(9);
                doc.setTextColor(80, 80, 80);
                const reportDate = new Date().toLocaleDateString();
                const documentId = generateDocumentId();
                doc.text(`Report ID: ${documentId}`, pageWidth - 75, metadataY + 8);
                doc.text(`Generated: ${reportDate}`, pageWidth - 75, metadataY + 15);
                doc.text(`Classification: Confidential`, pageWidth - 75, metadataY + 22);

                let yPos = headerHeight + 45; // Adjusted starting position for content

                // Get data from Laravel
                const backdoorResult = @json($backdoorResult['details']);
                const descriptions = @json($descriptions);

                if (!backdoorResult || Object.keys(backdoorResult).length === 0) {
                    doc.setFont("helvetica", "normal");
                    doc.setFontSize(12);
                    doc.text("No security analysis data available.", margin, yPos);
                    addEnhancedFooter(doc);
                    doc.save("Laporan-Analisis-Backdoor.pdf");
                    return;
                }

                // Content sections
                Object.keys(backdoorResult).forEach((key, index) => {
                    let title = key.replace(/_/g, " ").toUpperCase();

                    // Section header with clean styling
                    doc.setFillColor(41, 58, 74);
                    doc.roundedRect(margin, yPos - 5, pageWidth - (margin * 2), 12, 2, 2, 'F');

                    doc.setFont("helvetica", "bold");
                    doc.setFontSize(12);
                    doc.setTextColor(255, 255, 255);
                    doc.text(title, margin + 5, yPos + 3);
                    yPos += 12;

                    // Table data preparation
                    let data = [];
                    Object.keys(backdoorResult[key]).forEach((subKey) => {
                        let subTitle = subKey.replace(/_/g, " ");
                        let value = backdoorResult[key][subKey];

                        if (typeof value === 'object') {
                            value = JSON.stringify(value, null, 2)
                                .replace(/[\{\}"]/g, '')
                                .trim();
                        }

                        data.push([subTitle, value]);
                    });

                    // Clean table styling
                    doc.autoTable({
                        startY: yPos,
                        head: [
                            ["Kategori", "Detail"]
                        ],
                        body: data,
                        theme: "grid",
                        styles: {
                            fontSize: 9,
                            cellPadding: 6,
                            font: "helvetica",
                            lineWidth: 0.1
                        },
                        headStyles: {
                            fillColor: [41, 58, 74],
                            textColor: [255, 255, 255],
                            fontSize: 10,
                            fontStyle: 'bold',
                            halign: 'left'
                        },
                        columnStyles: {
                            0: {
                                fontStyle: 'bold',
                                fillColor: [245, 247, 250],
                                cellWidth: 60
                            },
                            1: {
                                fillColor: [255, 255, 255],
                                cellWidth: 'auto'
                            }
                        },
                        margin: {
                            left: margin,
                            right: margin
                        },
                        didDrawCell: function(data) {
                            if (data.cell.section === 'body') {
                                doc.setDrawColor(220, 220, 220);
                                doc.setLineWidth(0.1);
                                doc.line(
                                    data.cell.x,
                                    data.cell.y + data.cell.height,
                                    data.cell.x + data.cell.width,
                                    data.cell.y + data.cell.height
                                );
                            }
                        }
                    });

                    yPos = doc.lastAutoTable.finalY + 15;

                    // Clean section separator
                    if (index < Object.keys(backdoorResult).length - 1) {
                        doc.setDrawColor(220, 220, 220);
                        doc.setLineWidth(0.5);
                        doc.line(margin, yPos - 7, pageWidth - margin, yPos - 7);
                    }
                });

                addEnhancedFooter(doc);
                doc.save("Laporan-Analisis-Backdoor.pdf");
            });
        }

        // Enhanced footer function with clean styling
        function addEnhancedFooter(doc) {
            const pageCount = doc.internal.getNumberOfPages();
            const pageWidth = doc.internal.pageSize.width;
            const margin = 10;

            for (let i = 1; i <= pageCount; i++) {
                doc.setPage(i);

                // Clean footer background
                doc.setFillColor(245, 247, 250);
                doc.rect(0, doc.internal.pageSize.height - 20, pageWidth, 20, 'F');

                doc.setFontSize(8);
                doc.setTextColor(100, 100, 100);
                doc.text(
                    `Page ${i} of ${pageCount}`,
                    margin,
                    doc.internal.pageSize.height - 10
                );

                doc.text(
                    'Generated by CSIRT Scanning System',
                    pageWidth - margin,
                    doc.internal.pageSize.height - 10, {
                        align: 'right'
                    }
                );

                // Clean footer separator
                doc.setDrawColor(220, 220, 220);
                doc.setLineWidth(0.5);
                doc.line(0, doc.internal.pageSize.height - 20, pageWidth, doc.internal.pageSize.height - 20);
            }
        }

        // Helper functions remain unchanged
        function generateDocumentId() {
            return 'SEC-' + new Date().getTime().toString(36).toUpperCase();
        }

        function getBase64Image(url, callback) {
            var img = new Image();
            img.crossOrigin = "Anonymous";
            img.onload = function() {
                var canvas = document.createElement("canvas");
                canvas.width = this.width;
                canvas.height = this.height;
                var ctx = canvas.getContext("2d");
                ctx.drawImage(this, 0, 0);
                var dataURL = canvas.toDataURL("image/png");
                callback(dataURL);
            };
            img.src = url;
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
            // Clear form resubmission flags
            if (window.performance && window.performance.navigation.type === window.performance.navigation
                .TYPE_BACK_FORWARD) {
                window.location.replace(document.referrer);
                return;
            }

            // Hapus status POST dari history jika ada
            window.history.replaceState(null, '', window.location.href);

            // Lakukan navigasi back
            window.history.back();

            // Fallback jika masih di halaman yang sama
            const currentPage = window.location.href;
            setTimeout(() => {
                if (window.location.href === currentPage) {
                    window.location.replace(document.referrer || '/');
                }
            }, 100);
        }

        // Tambahkan event listener untuk mencegah form resubmission
        window.addEventListener('pageshow', (event) => {
            if (event.persisted || (window.performance && window.performance.navigation.type === window.performance
                    .navigation.TYPE_BACK_FORWARD)) {
                // Hapus status POST
                window.history.replaceState(null, '', window.location.href);
            }
        });
    </script>
</body>

</html>
