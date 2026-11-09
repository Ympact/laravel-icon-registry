import { execFile } from 'node:child_process';
import * as vscode from 'vscode';
import { type IndexedIcon, type Manifest, parseManifest } from './manifest.js';
import { CONTEXT_LENGTH, iconKeyPrefix, searchIcons } from './matching.js';

const LANGUAGES = ['blade', 'laravel-blade', 'php'];
const EXPORT_DEBOUNCE_MS = 1000;
const EXPORT_TIMEOUT_MS = 60_000;

/** One workspace folder's manifest and the watchers and export run that keep it current. */
class FolderIcons implements vscode.Disposable {
    manifest: Manifest | null = null;

    private readonly disposables: vscode.Disposable[] = [];
    private debounce: NodeJS.Timeout | undefined;
    private exporting = false;
    private exportAgain = false;

    constructor(readonly folder: vscode.WorkspaceFolder, private readonly log: vscode.OutputChannel) {}

    private get settings(): vscode.WorkspaceConfiguration {
        return vscode.workspace.getConfiguration('iconRegistry', this.folder.uri);
    }

    async start(): Promise<void> {
        const manifestPath = this.settings.get<string>('manifestPath', '');
        const manifestWatcher = vscode.workspace.createFileSystemWatcher(new vscode.RelativePattern(this.folder, manifestPath));
        const load = () => void this.load();

        this.disposables.push(manifestWatcher, manifestWatcher.onDidCreate(load), manifestWatcher.onDidChange(load));

        for (const pattern of this.settings.get<string[]>('watch', [])) {
            const watcher = vscode.workspace.createFileSystemWatcher(new vscode.RelativePattern(this.folder, pattern));
            const schedule = () => this.scheduleExport();

            this.disposables.push(watcher, watcher.onDidCreate(schedule), watcher.onDidChange(schedule), watcher.onDidDelete(schedule));
        }

        if (!(await this.load())) {
            this.export();
        }
    }

    /** Resolves to whether a readable manifest was found. */
    async load(): Promise<boolean> {
        const uri = vscode.Uri.joinPath(this.folder.uri, this.settings.get<string>('manifestPath', ''));

        try {
            this.manifest = parseManifest(Buffer.from(await vscode.workspace.fs.readFile(uri)).toString('utf8'));
        } catch {
            this.manifest = null;
        }

        return this.manifest !== null;
    }

    private scheduleExport(): void {
        clearTimeout(this.debounce);
        this.debounce = setTimeout(() => this.export(), EXPORT_DEBOUNCE_MS);
    }

    /** Runs the export unless the workspace is untrusted; a change during a run queues one more. */
    export(force = false): void {
        if (!vscode.workspace.isTrusted || (!force && !this.settings.get<boolean>('autoExport', true))) {
            return;
        }

        if (this.exporting) {
            this.exportAgain = true;

            return;
        }

        const [command, ...prefix] = this.settings.get<string[]>('phpCommand', ['php']);

        this.exporting = true;

        execFile(command, [...prefix, 'artisan', 'icon-registry:export'], { cwd: this.folder.uri.fsPath, timeout: EXPORT_TIMEOUT_MS }, (error, _stdout, stderr) => {
            this.exporting = false;

            if (error) {
                this.log.appendLine(`[${this.folder.name}] export failed: ${stderr || error.message}`);
            }

            if (this.exportAgain) {
                this.exportAgain = false;
                this.export(force);
            }
        });
    }

    dispose(): void {
        clearTimeout(this.debounce);
        this.disposables.forEach((disposable) => disposable.dispose());
    }
}

function completionItem(icon: IndexedIcon, position: number, range: vscode.Range, typed: string): vscode.CompletionItem {
    const item = new vscode.CompletionItem({ label: icon.key, description: icon.label }, vscode.CompletionItemKind.Value);
    const words = [...icon.aliases, ...icon.use_cases].join(', ');

    item.detail = icon.provider;
    item.documentation = words === '' ? undefined : words;
    item.range = range;
    // The list is already ranked and re-queried per keystroke, so stop the editor filtering on the key.
    item.filterText = typed;
    item.sortText = String(position).padStart(4, '0');

    return item;
}

export function activate(context: vscode.ExtensionContext): void {
    const log = vscode.window.createOutputChannel('Icon Registry');
    const folders = new Map<string, FolderIcons>();

    const sync = () => {
        folders.forEach((folder) => folder.dispose());
        folders.clear();

        for (const folder of vscode.workspace.workspaceFolders ?? []) {
            const icons = new FolderIcons(folder, log);

            folders.set(folder.uri.toString(), icons);
            void icons.start();
        }
    };

    const forDocument = (uri: vscode.Uri): FolderIcons | undefined => {
        const folder = vscode.workspace.getWorkspaceFolder(uri);

        return folder && folders.get(folder.uri.toString());
    };

    const provider: vscode.CompletionItemProvider = {
        provideCompletionItems(document, position) {
            const manifest = forDocument(document.uri)?.manifest;

            if (!manifest) {
                return undefined;
            }

            const offset = document.offsetAt(position);
            const start = document.positionAt(Math.max(0, offset - CONTEXT_LENGTH));
            const typed = iconKeyPrefix(document.getText(new vscode.Range(start, position)), manifest.component);

            if (typed === null) {
                return undefined;
            }

            const range = new vscode.Range(position.translate(0, -typed.length), position);
            const items = searchIcons(manifest.icons, typed).map((icon, index) => completionItem(icon, index, range, typed));

            return new vscode.CompletionList(items, true);
        },
    };

    context.subscriptions.push(
        log,
        { dispose: () => folders.forEach((folder) => folder.dispose()) },
        vscode.workspace.onDidChangeWorkspaceFolders(sync),
        vscode.workspace.onDidGrantWorkspaceTrust(sync),
        vscode.languages.registerCompletionItemProvider(LANGUAGES, provider, '"', "'", '.', '-'),
        vscode.commands.registerCommand('iconRegistry.refresh', () => folders.forEach((folder) => folder.export(true))),
        vscode.commands.registerCommand('iconRegistry.search', async () => {
            const editor = vscode.window.activeTextEditor;
            const manifest = editor && forDocument(editor.document.uri)?.manifest;

            if (!manifest) {
                void vscode.window.showWarningMessage('No icon manifest found. Run php artisan icon-registry:export.');

                return;
            }

            const picked = await vscode.window.showQuickPick(
                manifest.icons.map((icon) => ({ label: icon.key, description: icon.label, detail: icon.aliases.join(', ') || undefined })),
                { placeHolder: 'Search icons by key, label or alias', matchOnDescription: true, matchOnDetail: true },
            );

            if (picked) {
                await editor.insertSnippet(new vscode.SnippetString().appendText(picked.label));
            }
        }),
    );

    sync();
}

export function deactivate(): void {}
