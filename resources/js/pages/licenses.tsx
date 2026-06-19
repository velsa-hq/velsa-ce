import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';
import { home } from '@/routes';

type Package = {
    name: string;
    version: string;
    license: string;
    homepage: string | null;
    description: string | null;
};

type Props = {
    app_name: string;
    php: Package[];
    js: Package[];
};

export default function Licenses({ app_name, php, js }: Props) {
    const [query, setQuery] = useState('');

    const { phpFiltered, jsFiltered } = useMemo(() => {
        const q = query.trim().toLowerCase();

        if (!q) {
            return { phpFiltered: php, jsFiltered: js };
        }

        const match = (p: Package) =>
            p.name.toLowerCase().includes(q) ||
            p.license.toLowerCase().includes(q);

        return {
            phpFiltered: php.filter(match),
            jsFiltered: js.filter(match),
        };
    }, [query, php, js]);

    const totalPhp = php.length;
    const totalJs = js.length;

    return (
        <>
            <Head title={`Open source licenses · ${app_name}`} />

            <div className="min-h-screen bg-background text-foreground">
                <header className="border-b border-border">
                    <div className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4">
                        <Link
                            href={home().url}
                            className="text-sm font-semibold hover:opacity-80"
                        >
                            {app_name}
                        </Link>
                        <span className="text-xs text-muted-foreground">
                            {totalPhp + totalJs} third-party packages
                        </span>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-6xl px-6 py-10">
                    <h1 className="mb-2 text-3xl font-semibold tracking-tight">
                        Open source acknowledgements
                    </h1>
                    <p className="mb-6 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                        {app_name} is built on the work of hundreds of open
                        source projects. Each package below ships in the
                        production runtime under the listed license, which
                        permits commercial and proprietary distribution. Full
                        license texts travel with each package inside the
                        deployed image.
                    </p>

                    <div className="mb-6 max-w-md">
                        <Input
                            type="search"
                            placeholder="Filter by package name or license..."
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            aria-label="Filter packages"
                        />
                    </div>

                    <Section
                        title="PHP (Composer)"
                        total={totalPhp}
                        shown={phpFiltered.length}
                        packages={phpFiltered}
                    />

                    <Section
                        title="JavaScript (npm)"
                        total={totalJs}
                        shown={jsFiltered.length}
                        packages={jsFiltered}
                    />
                </main>
            </div>
        </>
    );
}

function Section({
    title,
    total,
    shown,
    packages,
}: {
    title: string;
    total: number;
    shown: number;
    packages: Package[];
}) {
    return (
        <section className="mb-12">
            <h2 className="mb-4 flex items-baseline gap-3 text-xl font-semibold">
                {title}
                <span className="text-xs font-normal text-muted-foreground">
                    {shown === total ? `${total}` : `${shown} of ${total}`}
                </span>
            </h2>

            <div className="overflow-hidden rounded-lg border border-border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-4 py-2 text-left font-medium">
                                Package
                            </th>
                            <th className="px-4 py-2 text-left font-medium">
                                Version
                            </th>
                            <th className="px-4 py-2 text-left font-medium">
                                License
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {packages.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={3}
                                    className="px-4 py-6 text-center text-muted-foreground"
                                >
                                    No matches.
                                </td>
                            </tr>
                        ) : (
                            packages.map((p) => (
                                <tr
                                    key={`${p.name}@${p.version}`}
                                    className="border-t border-border"
                                >
                                    <td className="px-4 py-2 font-mono text-xs">
                                        {p.homepage ? (
                                            <a
                                                href={p.homepage}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-primary hover:underline"
                                            >
                                                {p.name}
                                            </a>
                                        ) : (
                                            p.name
                                        )}
                                    </td>
                                    <td className="px-4 py-2 font-mono text-xs text-muted-foreground">
                                        {p.version}
                                    </td>
                                    <td className="px-4 py-2 text-xs">
                                        {p.license}
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
