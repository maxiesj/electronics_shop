import fs from "node:fs/promises";
import { FileBlob, SpreadsheetFile } from "@oai/artifact-tool";

const inputPath = "C:/Users/maxie/Downloads/2026 - KENYA - BANK ACCOUNT LINKING MAIN.xlsx";
const outputDir = "D:/xammp/htdocs/electronics_shop/outputs/formula_repair";
const input = await FileBlob.load(inputPath);
const workbook = await SpreadsheetFile.importXlsx(input);

console.log((await workbook.inspect({ kind: "sheet", include: "id,name", maxChars: 6000 })).ndjson);

const pairs = [
  ["2024 KENYA BANK LINKING", "KENYA BANK LINKING"],
  ["LINKING FORM", "LINKING FORM 1"],
];

for (const [sourceName, targetName] of pairs) {
  const source = workbook.worksheets.getItem(sourceName);
  const target = workbook.worksheets.getItem(targetName);
  const sourceUsed = source.getUsedRange();
  const targetUsed = target.getUsedRange();
  console.log(JSON.stringify({
    pair: [sourceName, targetName],
    sourceAddress: sourceUsed?.address,
    targetAddress: targetUsed?.address,
  }));
  console.log((await workbook.inspect({kind:"formula", sheetId:sourceName, maxChars:12000, options:{maxResults:5000}})).ndjson);
  console.log((await workbook.inspect({kind:"formula", sheetId:targetName, maxChars:12000, options:{maxResults:5000}})).ndjson);
}

await fs.mkdir(outputDir, { recursive: true });
for (const name of pairs.flat()) {
  const preview = await workbook.render({ sheetName: name, autoCrop: "all", scale: 0.7, format: "png" });
  await fs.writeFile(`${outputDir}/${name.replaceAll(" ", "_")}-before.png`, new Uint8Array(await preview.arrayBuffer()));
}
