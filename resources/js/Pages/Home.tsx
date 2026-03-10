import React from "react";
import { Head } from "@inertiajs/react";
import { IconBolt, IconArrowRight, IconBook, IconCode, IconRefresh, IconServer } from "@tabler/icons-react";

export default function Home() {
    return (
        <div className="min-h-screen bg-gradient-to-br from-white via-sky-50/40 to-sky-100/30 flex flex-col">
            <Head title="Home" />

            <header className="px-8 py-4 flex items-center justify-between border-b border-sky-100 bg-white/80 backdrop-blur-sm sticky top-0 z-10">
                <div className="flex items-center gap-2.5">
                    <div className="flex items-center justify-center h-8 w-8 rounded-lg bg-sky-600 shadow-sm shadow-sky-200">
                        <IconBolt className="w-4 h-4 text-white" />
                    </div>
                    <span className="text-sm font-bold text-gray-800 tracking-tight">
                        MyApp
                    </span>
                </div>
                <nav className="flex items-center gap-2">
                    <button
                        onClick={() => console.log('Dashboard di navbar di-klik')}
                        className="h-8 px-3.5 inline-flex items-center gap-1.5 rounded-lg text-sm font-medium text-gray-500 hover:text-sky-600 hover:bg-sky-50 transition-all"
                    >
                        Dashboard
                    </button>
                    <button
                        onClick={() => console.log('Sign In di navbar di-klik')}
                        className="h-8 px-3.5 inline-flex items-center gap-1.5 rounded-lg bg-sky-600 text-white text-sm font-medium hover:bg-sky-700 transition-all shadow-sm"
                    >
                        Sign In
                    </button>
                </nav>
            </header>

            <main className="flex-1 flex flex-col items-center justify-center px-6 py-20 text-center">

                <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-sky-50 border border-sky-100 text-sky-600 text-xs font-medium mb-6 shadow-sm">
                    <span className="w-1.5 h-1.5 rounded-full bg-sky-400 animate-pulse" />
                    Setup completed successfully
                </div>

                <h1 className="text-4xl md:text-5xl font-bold text-gray-900 tracking-tight leading-tight max-w-xl">
                    Welcome to{" "}
                    <span className="text-sky-600">
                        Inertia React
                    </span>{" "}
                    App
                </h1>

                <p className="mt-4 text-base text-gray-400 max-w-md leading-relaxed">
                    Your application is up and running. Start building something amazing with React, Inertia, and Laravel.
                </p>

                <div className="mt-8 flex flex-col sm:flex-row items-center gap-3">
                    <button
                        onClick={() => console.log('Tombol CTA Go to Dashboard di-klik')}
                        className="inline-flex items-center gap-2 h-10 px-5 rounded-lg bg-sky-600 text-white text-sm font-medium hover:bg-sky-700 transition-all shadow-sm shadow-sky-200"
                    >
                        Go to Dashboard
                        <IconArrowRight className="w-4 h-4" />
                    </button>
                    <a
                        href="https://inertiajs.com"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-2 h-10 px-5 rounded-lg border border-sky-100 bg-white text-sky-600 text-sm font-medium hover:bg-sky-50 hover:border-sky-300 transition-all"
                    >
                        <IconBook className="w-4 h-4" />
                        Read the Docs
                    </a>
                </div>

                <div className="mt-16 grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-2xl w-full">
                    {[
                        {
                            icon: <IconCode className="w-5 h-5 text-sky-500" />,
                            title: "React + TypeScript",
                            desc: "Type-safe components with the full power of React.",
                        },
                        {
                            icon: <IconRefresh className="w-5 h-5 text-sky-500" />,
                            title: "Inertia.js",
                            desc: "SPA-like experience without building a separate API.",
                        },
                        {
                            icon: <IconServer className="w-5 h-5 text-sky-500" />,
                            title: "Laravel Backend",
                            desc: "Robust server-side logic with seamless integration.",
                        },
                    ].map((f) => (
                        <div
                            key={f.title}
                            className="flex flex-col items-start gap-3 p-5 rounded-xl border border-sky-100 bg-white shadow-sm hover:shadow-md hover:border-sky-200 transition-all text-left"
                        >
                            <div className="p-2 rounded-lg bg-sky-50 border border-sky-100">
                                {f.icon}
                            </div>
                            <div>
                                <p className="text-sm font-semibold text-gray-800">{f.title}</p>
                                <p className="text-xs text-gray-400 mt-0.5 leading-relaxed">{f.desc}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </main>

            <footer className="py-5 text-center border-t border-sky-50">
                <p className="text-xs text-gray-400">
                    Built with ❤️ using React, Inertia.js & Laravel
                </p>
            </footer>
        </div>
    );
}