
"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("aiAssistantForm");
    const input = document.getElementById("aiMessage");
    const button = document.getElementById("aiAskButton");
    const responseBox = document.getElementById("aiResponse");

    if (!form || !input || !button || !responseBox) {
        console.error("BizPilot AI: Required HTML elements were not found.");
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Safely render inline Markdown
    |--------------------------------------------------------------------------
    */

    function renderInline(text, parent) {
        const value = String(text ?? "");
        const pattern = /(\*\*[^*]+\*\*|\*[^*]+\*|`[^`]+`)/g;

        let lastIndex = 0;
        let match;

        while ((match = pattern.exec(value)) !== null) {
            parent.appendChild(
                document.createTextNode(value.slice(lastIndex, match.index))
            );

            const token = match[0];
            let element;
            let content;

            if (token.startsWith("**")) {
                element = document.createElement("strong");
                content = token.slice(2, -2);
            } else if (token.startsWith("*")) {
                element = document.createElement("em");
                content = token.slice(1, -1);
            } else {
                element = document.createElement("code");
                content = token.slice(1, -1);
            }

            element.textContent = content;
            parent.appendChild(element);

            lastIndex = pattern.lastIndex;
        }

        parent.appendChild(
            document.createTextNode(value.slice(lastIndex))
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Parse Markdown table rows
    |--------------------------------------------------------------------------
    */

    function splitTableRow(line) {
        let value = line.trim()
            .replace(/^```(?:markdown|md)?\s*/i, "")
            .replace(/\s*```$/, "")
            .replace(/\\\|/g, "\u0000");

        if (value.startsWith("|")) {
            value = value.slice(1);
        }

        if (value.endsWith("|")) {
            value = value.slice(0, -1);
        }

        return value.split("|").map(function (cell) {
            return cell.replace(/\u0000/g, "|").trim();
        });
    }

    function isSeparator(line) {
        const cells = splitTableRow(line);

        return cells.length > 0 && cells.every(function (cell) {
            return /^:?-{3,}:?$/.test(cell);
        });
    }

    function isTableRow(line) {
        return line.includes("|") &&
            splitTableRow(line).length >= 2;
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize table headers
    |--------------------------------------------------------------------------
    */

    function normalizeHeader(header) {
        return String(header ?? "")
            .replace(/\*\*/g, "")
            .replace(/__/g, "")
            .toLowerCase()
            .replace(
                /[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}]/gu,
                ""
            )
            .replace(/[^a-z0-9 ]/g, " ")
            .replace(/\s+/g, " ")
            .trim();
    }

    /*
    |--------------------------------------------------------------------------
    | Identify product columns
    |--------------------------------------------------------------------------
    */

    function getProductColumn(header) {
        const value = normalizeHeader(header);

        if (/^(product name|product|item name|item)$/.test(value)) {
            return "name";
        }

        if (/^(category|product category)$/.test(value)) {
            return "category";
        }

        if (/^(price|price lkr|price in lkr|unit price)$/.test(value)) {
            return "price";
        }

        if (/^(stock|stock quantity|available stock|quantity|qty)$/.test(value)) {
            return "stock";
        }

        if (/^(availability|stock status|status)$/.test(value)) {
            return "availability";
        }

        if (/^(description|product description|details)$/.test(value)) {
            return "description";
        }

        return null;
    }

    function isProductHeader(cells) {
        const recognized = cells.map(getProductColumn);

        return recognized.includes("name") &&
            recognized.includes("category") &&
            recognized.includes("price") &&
            recognized.includes("stock");
    }

    /*
    |--------------------------------------------------------------------------
    | Render product table - SIX COLUMNS
    |--------------------------------------------------------------------------
    */

    function renderProductTable(headerCells, dataRows, parent) {
        const columns = headerCells.map(getProductColumn);

        const tableColumns = [
            ["name", "Product"],
            ["category", "Category"],
            ["price", "Price (LKR)"],
            ["stock", "Stock"],
            ["availability", "Availability"],
            ["description", "Description"]
        ];

        const table = document.createElement("table");
        table.className = "ai-response-table";

        const thead = document.createElement("thead");
        const headerRow = document.createElement("tr");

        tableColumns.forEach(function (column) {
            const th = document.createElement("th");
            th.textContent = column[1];
            headerRow.appendChild(th);
        });

        thead.appendChild(headerRow);
        table.appendChild(thead);

        const tbody = document.createElement("tbody");

        dataRows.forEach(function (cells) {
            const mapped = {};

            columns.forEach(function (column, index) {
                if (column && mapped[column] === undefined) {
                    mapped[column] = cells[index] ?? "";
                }
            });

            if (
                !String(mapped.name ?? "").trim() ||
                !String(mapped.category ?? "").trim()
            ) {
                return;
            }

            // If the response does not provide availability,
            // derive a display label from the stock value.
            if (!String(mapped.availability ?? "").trim()) {
                const stockText = String(mapped.stock ?? "").trim();
                const stockNumber = Number(stockText.replace(/,/g, ""));

                if (stockText !== "" && Number.isFinite(stockNumber)) {
                    mapped.availability =
                        stockNumber > 0 ? "In Stock" : "Out of Stock";
                }
            }

            const row = document.createElement("tr");

            tableColumns.forEach(function (column) {
                const td = document.createElement("td");
                const value = String(mapped[column[0]] ?? "").trim();

                td.textContent = value || "—";
                row.appendChild(td);
            });

            tbody.appendChild(row);
        });

        if (tbody.children.length === 0) {
            const row = document.createElement("tr");
            const cell = document.createElement("td");

            cell.colSpan = 6;
            cell.textContent = "No valid product rows were provided.";

            row.appendChild(cell);
            tbody.appendChild(row);
        }

        table.appendChild(tbody);
        parent.appendChild(table);
    }

    /*
    |--------------------------------------------------------------------------
    | Render general Markdown tables
    |--------------------------------------------------------------------------
    */

    function renderGeneralTable(headerCells, dataRows, parent) {
        const table = document.createElement("table");
        table.className = "ai-response-table";

        const thead = document.createElement("thead");
        const headerRow = document.createElement("tr");

        headerCells.forEach(function (value) {
            const th = document.createElement("th");
            renderInline(value, th);
            headerRow.appendChild(th);
        });

        thead.appendChild(headerRow);
        table.appendChild(thead);

        const tbody = document.createElement("tbody");

        dataRows.forEach(function (cells) {
            const row = document.createElement("tr");

            headerCells.forEach(function (_, index) {
                const td = document.createElement("td");
                renderInline(cells[index] ?? "—", td);
                row.appendChild(td);
            });

            tbody.appendChild(row);
        });

        table.appendChild(tbody);
        parent.appendChild(table);
    }

    /*
    |--------------------------------------------------------------------------
    | Render Markdown table block
    |--------------------------------------------------------------------------
    */

    function renderTableBlock(lines, start, parent) {
        let index = start;
        const rows = [];

        while (
            index < lines.length &&
            isTableRow(lines[index].trim())
        ) {
            const line = lines[index].trim();

            if (!isSeparator(line)) {
                rows.push(splitTableRow(line));
            }

            index++;
        }

        if (rows.length < 2) {
            const paragraph = document.createElement("p");
            renderInline(lines[start].trim(), paragraph);
            parent.appendChild(paragraph);
            return start + 1;
        }

        const headerCells = rows[0];
        const dataRows = rows.slice(1);

        if (isProductHeader(headerCells)) {
            renderProductTable(headerCells, dataRows, parent);
        } else {
            renderGeneralTable(headerCells, dataRows, parent);
        }

        return index;
    }

    /*
    |--------------------------------------------------------------------------
    | Render complete AI response
    |--------------------------------------------------------------------------
    */

    function renderAnswer(text) {
        const fragment = document.createDocumentFragment();

        const normalized = String(text ?? "")
            .replace(/\r\n/g, "\n")
            .replace(/\\\r?\n/g, "\n")
            .replace(/\\\*\*/g, "**")
            .replace(/\\\*/g, "*")
            .trim();

        const lines = normalized.split("\n");
        let index = 0;

        while (index < lines.length) {
            const line = lines[index].trim();

            if (!line) {
                index++;
                continue;
            }

            if (isTableRow(line)) {
                index = renderTableBlock(lines, index, fragment);
                continue;
            }

            if (isSeparator(line)) {
                index++;
                continue;
            }

            if (/^```/.test(line)) {
                index++;
                continue;
            }

            const heading = line.match(/^(#{1,3})\s+(.+)$/);

            if (heading) {
                const element = document.createElement(
                    "h" + heading[1].length
                );

                renderInline(heading[2], element);
                fragment.appendChild(element);
                index++;
                continue;
            }

            if (/^[-*]\s+/.test(line)) {
                const list = document.createElement("ul");

                while (
                    index < lines.length &&
                    /^[-*]\s+/.test(lines[index].trim())
                ) {
                    const li = document.createElement("li");

                    renderInline(
                        lines[index].trim().replace(/^[-*]\s+/, ""),
                        li
                    );

                    list.appendChild(li);
                    index++;
                }

                fragment.appendChild(list);
                continue;
            }

            if (/^\d+[.)]\s+/.test(line)) {
                const list = document.createElement("ol");

                while (
                    index < lines.length &&
                    /^\d+[.)]\s+/.test(lines[index].trim())
                ) {
                    const li = document.createElement("li");

                    renderInline(
                        lines[index].trim().replace(/^\d+[.)]\s+/, ""),
                        li
                    );

                    list.appendChild(li);
                    index++;
                }

                fragment.appendChild(list);
                continue;
            }

            const paragraph = document.createElement("p");
            renderInline(line, paragraph);
            fragment.appendChild(paragraph);
            index++;
        }

        if (!fragment.hasChildNodes()) {
            fragment.appendChild(
                document.createTextNode("No response was received.")
            );
        }

        responseBox.replaceChildren(fragment);
    }

    /*
    |--------------------------------------------------------------------------
    | Submit message to PHP backend
    |--------------------------------------------------------------------------
    */

    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        const message = input.value.trim();

        if (!message) {
            responseBox.textContent = "Please enter a message.";
            input.focus();
            return;
        }

        if (message.length > 1000) {
            responseBox.textContent =
                "Please keep your message under 1,000 characters.";
            input.focus();
            return;
        }

        button.disabled = true;
        input.disabled = true;
        button.textContent = "Thinking...";
        responseBox.textContent = "🤖 BizPilot AI is thinking...";

        try {
            const response = await fetch(
                "actions/ai_assistant.php",
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({
                        message: message
                    })
                }
            );

            let result;

            try {
                result = await response.json();
            } catch {
                throw new Error(
                    "The server returned an invalid response. " +
                    "Check the PHP error log."
                );
            }

            if (
                !response.ok ||
                !result ||
                result.success !== true
            ) {
                throw new Error(
                    result && result.message
                        ? result.message
                        : "The AI request failed. Please try again."
                );
            }

            if (
                typeof result.message !== "string" ||
                !result.message.trim()
            ) {
                throw new Error(
                    "The AI returned an empty response. Please try again."
                );
            }

            renderAnswer("🤖 " + result.message);
            input.value = "";

        } catch (error) {
            console.error("BizPilot AI error:", error);

            responseBox.textContent =
                error.message ||
                "Unable to connect to the AI assistant. Please try again.";

        } finally {
            button.disabled = false;
            input.disabled = false;
            button.textContent = "Ask AI";
            input.focus();
        }
    });
});
