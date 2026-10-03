$(document).ready(function () {

    'use strict';
    var brandPrimary = '#4f46e5';
    var brandPrimaryRgba = 'rgba(79, 70, 229, 0.2)';

    var isDark = $('body').hasClass('dark-mode');
    var gridColor = isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(226, 232, 240, 0.6)';
    var tickColor = isDark ? '#64748b' : '#94a3b8';

    // ------------------------------------------------------- //
    // Modern Line Chart: Cash Flow
    // ------------------------------------------------------ //
    var CASHFLOW = $('#cashFlow');
    if (CASHFLOW.length > 0) {
        var recieved = CASHFLOW.data('recieved');
        var sent = CASHFLOW.data('sent');
        var month = CASHFLOW.data('month');
        var label1 = CASHFLOW.data('label1') || 'Payment Received';
        var label2 = CASHFLOW.data('label2') || 'Payment Sent';

        var ctx = CASHFLOW[0].getContext('2d');
        var gradInflow = ctx.createLinearGradient(0, 0, 0, 280);
        gradInflow.addColorStop(0, 'rgba(99, 102, 241, 0.25)');
        gradInflow.addColorStop(1, 'rgba(99, 102, 241, 0.00)');

        var gradOutflow = ctx.createLinearGradient(0, 0, 0, 280);
        gradOutflow.addColorStop(0, 'rgba(249, 115, 22, 0.22)');
        gradOutflow.addColorStop(1, 'rgba(249, 115, 22, 0.00)');

        var cashFlow_chart = new Chart(CASHFLOW, {
            type: 'line',
            data: {
                labels: month,
                datasets: [
                    {
                        label: label1,
                        fill: true,
                        lineTension: 0.4,
                        backgroundColor: gradInflow,
                        borderColor: '#6366f1',
                        borderWidth: 2.8,
                        pointBorderColor: '#6366f1',
                        pointBackgroundColor: "#ffffff",
                        pointBorderWidth: 2.5,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#6366f1',
                        pointHoverBorderColor: "#ffffff",
                        pointHoverBorderWidth: 2,
                        pointRadius: 4,
                        pointHitRadius: 10,
                        data: recieved,
                        spanGaps: false
                    },
                    {
                        label: label2,
                        fill: true,
                        lineTension: 0.4,
                        backgroundColor: gradOutflow,
                        borderColor: '#f97316',
                        borderWidth: 2.8,
                        pointBorderColor: '#f97316',
                        pointBackgroundColor: "#ffffff",
                        pointBorderWidth: 2.5,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#f97316',
                        pointHoverBorderColor: "#ffffff",
                        pointHoverBorderWidth: 2,
                        pointRadius: 4,
                        pointHitRadius: 10,
                        data: sent,
                        spanGaps: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 10,
                        usePointStyle: true,
                        fontFamily: 'Plus Jakarta Sans',
                        fontSize: 12,
                        fontColor: tickColor,
                        padding: 16
                    }
                },
                tooltips: {
                    backgroundColor: '#0f172a',
                    titleFontFamily: 'Plus Jakarta Sans',
                    bodyFontFamily: 'JetBrains Mono',
                    titleFontSize: 12.5,
                    bodyFontSize: 12,
                    cornerRadius: 8,
                    xPadding: 12,
                    yPadding: 10,
                    caretSize: 6,
                    displayColors: true
                },
                scales: {
                    xAxes: [{
                        gridLines: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            fontFamily: 'Plus Jakarta Sans',
                            fontSize: 11,
                            fontColor: tickColor
                        }
                    }],
                    yAxes: [{
                        gridLines: {
                            color: gridColor,
                            zeroLineColor: gridColor,
                            drawBorder: false
                        },
                        ticks: {
                            fontFamily: 'JetBrains Mono',
                            fontSize: 11,
                            fontColor: tickColor,
                            beginAtZero: true
                        }
                    }]
                }
            }
        });
    };

    // ------------------------------------------------------- //
    // Sale Report Line Chart
    // ------------------------------------------------------ //
    var SALEREPORTCHART = $('#sale-report-chart');
    if (SALEREPORTCHART.length > 0) {
        var soldqty = SALEREPORTCHART.data('soldqty');
        var datepoints = SALEREPORTCHART.data('datepoints');
        var label1 = SALEREPORTCHART.data('label1') || 'Sold Qty';

        var ctxReport = SALEREPORTCHART[0].getContext('2d');
        var gradReport = ctxReport.createLinearGradient(0, 0, 0, 260);
        gradReport.addColorStop(0, 'rgba(79, 70, 229, 0.25)');
        gradReport.addColorStop(1, 'rgba(79, 70, 229, 0.00)');

        var sale_report_chart = new Chart(SALEREPORTCHART, {
            type: 'line',
            data: {
                labels: datepoints,
                datasets: [
                    {
                        label: label1,
                        fill: true,
                        lineTension: 0.4,
                        backgroundColor: gradReport,
                        borderColor: '#4f46e5',
                        borderWidth: 2.8,
                        pointBorderColor: '#4f46e5',
                        pointBackgroundColor: "#ffffff",
                        pointBorderWidth: 2.5,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#4f46e5',
                        pointHoverBorderColor: "#ffffff",
                        pointHoverBorderWidth: 2,
                        pointRadius: 4,
                        pointHitRadius: 10,
                        data: soldqty,
                        spanGaps: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 10,
                        usePointStyle: true,
                        fontFamily: 'Plus Jakarta Sans',
                        fontSize: 12,
                        fontColor: tickColor
                    }
                },
                scales: {
                    xAxes: [{
                        gridLines: { display: false, drawBorder: false },
                        ticks: { fontFamily: 'Plus Jakarta Sans', fontSize: 11, fontColor: tickColor }
                    }],
                    yAxes: [{
                        gridLines: { color: gridColor, drawBorder: false },
                        ticks: { fontFamily: 'JetBrains Mono', fontSize: 11, fontColor: tickColor, beginAtZero: true }
                    }]
                }
            }
        });
    };

    // ------------------------------------------------------- //
    // Modern Bar Chart: Yearly Sales vs Purchases
    // ------------------------------------------------------ //
    var SALECHART = $('#saleChart');
    if (SALECHART.length > 0) {
        var yearly_sale_amount = SALECHART.data('sale_chart_value');
        var yearly_purchase_amount = SALECHART.data('purchase_chart_value');
        var label1 = SALECHART.data('label1') || 'Purchases';
        var label2 = SALECHART.data('label2') || 'Sales';

        var ctxBar = SALECHART[0].getContext('2d');
        var gradBarSale = ctxBar.createLinearGradient(0, 0, 0, 300);
        gradBarSale.addColorStop(0, '#6366f1');
        gradBarSale.addColorStop(1, '#4f46e5');

        var gradBarPurchase = ctxBar.createLinearGradient(0, 0, 0, 300);
        gradBarPurchase.addColorStop(0, '#f97316');
        gradBarPurchase.addColorStop(1, '#ea580c');

        var saleChart = new Chart(SALECHART, {
            type: 'bar',
            data: {
                labels: ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"],
                datasets: [
                    {
                        label: label1,
                        backgroundColor: gradBarPurchase,
                        hoverBackgroundColor: '#f97316',
                        borderColor: 'transparent',
                        borderWidth: 0,
                        data: yearly_purchase_amount
                    },
                    {
                        label: label2,
                        backgroundColor: gradBarSale,
                        hoverBackgroundColor: '#6366f1',
                        borderColor: 'transparent',
                        borderWidth: 0,
                        data: yearly_sale_amount
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 10,
                        usePointStyle: true,
                        fontFamily: 'Plus Jakarta Sans',
                        fontSize: 12,
                        fontColor: tickColor,
                        padding: 16
                    }
                },
                tooltips: {
                    backgroundColor: '#0f172a',
                    titleFontFamily: 'Plus Jakarta Sans',
                    bodyFontFamily: 'JetBrains Mono',
                    titleFontSize: 12.5,
                    bodyFontSize: 12,
                    cornerRadius: 8,
                    xPadding: 12,
                    yPadding: 10
                },
                scales: {
                    xAxes: [{
                        barPercentage: 0.7,
                        categoryPercentage: 0.6,
                        gridLines: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            fontFamily: 'Plus Jakarta Sans',
                            fontSize: 11,
                            fontColor: tickColor
                        }
                    }],
                    yAxes: [{
                        gridLines: {
                            color: gridColor,
                            zeroLineColor: gridColor,
                            drawBorder: false
                        },
                        ticks: {
                            fontFamily: 'JetBrains Mono',
                            fontSize: 11,
                            fontColor: tickColor,
                            beginAtZero: true
                        }
                    }]
                }
            }
        });
    };

    // ------------------------------------------------------- //
    // Best Seller Bar Chart
    // ------------------------------------------------------ //
    var BESTSELLER = $('#bestSeller');
    if (BESTSELLER.length > 0) {
        var sold_qty = BESTSELLER.data('sold_qty');
        var product_info = BESTSELLER.data('product');

        var bestSeller = new Chart(BESTSELLER, {
            type: 'bar',
            data: {
                labels: [ product_info[0], product_info[1], product_info[2] ],
                datasets: [
                    {
                        label: "Sale Qty",
                        backgroundColor: ['#6366f1', '#10b981', '#f59e0b'],
                        hoverBackgroundColor: ['#4f46e5', '#059669', '#d97706'],
                        borderWidth: 0,
                        data: [ sold_qty[0], sold_qty[1], sold_qty[2] ]
                    }
                ]
            },
            options: {
                responsive: true,
                legend: { display: false },
                scales: {
                    xAxes: [{ gridLines: { display: false }, ticks: { fontColor: tickColor } }],
                    yAxes: [{ gridLines: { color: gridColor }, ticks: { fontColor: tickColor, beginAtZero: true } }]
                }
            }
        });
    };

    // ------------------------------------------------------- //
    // Pie Chart
    // ------------------------------------------------------ //
    var PIECHART = $('#pieChart');
    if (PIECHART.length > 0) {
        var price = PIECHART.data('price');
        var cost = PIECHART.data('cost');
        var label1 = PIECHART.data('label1') || 'Price';
        var label2 = PIECHART.data('label2') || 'Cost';
        var label3 = PIECHART.data('label3') || 'Profit';

        var myPieChart = new Chart(PIECHART, {
            type: 'doughnut',
            data: {
                labels: [ label1, label2, label3 ],
                datasets: [{
                    data: [ price, cost, Math.max(0, price - cost) ],
                    backgroundColor: ['#4f46e5', '#ef4444', '#10b981'],
                    hoverBackgroundColor: ['#4338ca', '#dc2626', '#059669'],
                    borderWidth: 0
                }]
            },
            options: {
                cutoutPercentage: 72,
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 10, usePointStyle: true, fontColor: tickColor }
                }
            }
        });
    }

    // ------------------------------------------------------- //
    // Modern Doughnut: Transaction Summary
    // ------------------------------------------------------ //
    var TRANSACTIONCHART = $('#transactionChart');
    if (TRANSACTIONCHART.length > 0) {
        var revenue = TRANSACTIONCHART.data('revenue');
        var purchase = TRANSACTIONCHART.data('purchase');
        var expense = TRANSACTIONCHART.data('expense');
        var label1 = TRANSACTIONCHART.data('label1') || 'Purchase';
        var label2 = TRANSACTIONCHART.data('label2') || 'Revenue';
        var label3 = TRANSACTIONCHART.data('label3') || 'Expense';

        var myTransactionChart = new Chart(TRANSACTIONCHART, {
            type: 'doughnut',
            data: {
                labels: [ label1, label2, label3 ],
                datasets: [{
                    data: [ purchase, revenue, expense ],
                    borderWidth: 0,
                    backgroundColor: [
                        '#6366f1',
                        '#10b981',
                        '#f59e0b'
                    ],
                    hoverBackgroundColor: [
                        '#4f46e5',
                        '#059669',
                        '#d97706'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutoutPercentage: 74,
                legend: {
                    display: false
                },
                tooltips: {
                    backgroundColor: '#0f172a',
                    titleFontFamily: 'Plus Jakarta Sans',
                    bodyFontFamily: 'JetBrains Mono',
                    cornerRadius: 8,
                    xPadding: 12,
                    yPadding: 10
                }
            }
        });
    }
});

