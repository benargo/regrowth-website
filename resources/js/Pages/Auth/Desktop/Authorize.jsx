import { useState } from "react";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";
import FormContainer from "@/Components/FormContainer";
import { PrimaryButton, SecondaryButton } from "@/Components/FormControls";
import { Can, Cannot } from "@/Components/Authorizable";

const BAN_PERMISSIONS = ["create-grm-bans", "update-grm-bans"];

const ROSTER_SENDS = [
    "character names, exactly as GRM stores them",
    "each character's GRM ID",
    "guild ranks",
    "levels (level 10 and above only)",
    "classes and races",
    "alt groups and their mains",
    "join and leave dates",
    "when GRM recorded each change",
];

const BAN_SENDS = ["bans: who was banned or unbanned, when and by whom", "ban reasons"];

const SYNC_SENDS = ["the guild's rank names", "the guild's GRM key", "the GRM version"];

const NEVER_SENDS = [
    "last online",
    "notes, officer notes and custom notes",
    "birthdays",
    "nicknames",
    "professions",
    "zones and when characters entered them",
    "the guild log",
    "other guilds and characters",
];

/**
 * Consent page for the desktop app's authorization code request, listing what the app sends
 * and never sends; ban data is listed as sent only to viewers who may create or update bans.
 * Approve and Deny are native form posts carrying the session CSRF token and Passport's auth
 * token. Once either form is submitted, both buttons are disabled and the submitted one shows
 * "Approving…" or "Denying…".
 */
export default function Authorize({ clientName, scopes, authToken, csrfToken }) {
    const [submittedAction, setSubmittedAction] = useState(null);
    const isSubmitting = submittedAction !== null;
    const clientNameLabel = <span translate="no">{clientName}</span>;

    return (
        <Master title="Sign in to Regrowth Desktop">
            <SharedHeader
                title="Sign in to Regrowth Desktop"
                subtitle={<>{clientNameLabel} wants to sign in to your account</>}
                backgroundClass="bg-officer-meeting"
            />

            <FormContainer maxWidth="lg">
                <h2 className="mb-2 text-xl font-bold">
                    <span translate="no" className="text-amber-500">
                        {clientName}
                    </span>{" "}
                    will be able to
                </h2>
                <ul className="mb-6 list-disc pl-6 text-gray-300">
                    {scopes.map((scope) => (
                        <li key={scope.id}>{scope.description}</li>
                    ))}
                </ul>

                <div className="mb-8 grid gap-6 text-sm sm:grid-cols-2">
                    <section>
                        <h3 className="mb-2 font-semibold text-gray-200">{clientNameLabel} sends</h3>
                        <ul className="list-disc pl-5 text-gray-300">
                            {ROSTER_SENDS.map((item) => (
                                <li key={item}>{item}</li>
                            ))}
                            <Can permission={BAN_PERMISSIONS}>
                                {BAN_SENDS.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </Can>
                            {SYNC_SENDS.map((item) => (
                                <li key={item}>{item}</li>
                            ))}
                        </ul>
                    </section>
                    <section>
                        <h3 className="mb-2 font-semibold text-gray-200">It never sends</h3>
                        <ul className="list-disc pl-5 text-gray-300">
                            {NEVER_SENDS.map((item) => (
                                <li key={item}>{item}</li>
                            ))}
                            <Cannot permission={BAN_PERMISSIONS}>
                                <li>bans and ban reasons</li>
                            </Cannot>
                        </ul>
                    </section>
                </div>

                <div className="flex gap-4">
                    <form
                        method="post"
                        action={route("passport.authorizations.approve")}
                        onSubmit={() => setSubmittedAction("approve")}
                    >
                        <input type="hidden" name="_token" value={csrfToken} />
                        <input type="hidden" name="auth_token" value={authToken} />
                        <PrimaryButton type="submit" processing={submittedAction === "approve"} disabled={isSubmitting}>
                            {submittedAction === "approve" ? "Approving…" : "Approve"}
                        </PrimaryButton>
                    </form>
                    <form
                        method="post"
                        action={route("passport.authorizations.deny")}
                        onSubmit={() => setSubmittedAction("deny")}
                    >
                        <input type="hidden" name="_method" value="DELETE" />
                        <input type="hidden" name="_token" value={csrfToken} />
                        <input type="hidden" name="auth_token" value={authToken} />
                        <SecondaryButton type="submit" processing={submittedAction === "deny"} disabled={isSubmitting}>
                            {submittedAction === "deny" ? "Denying…" : "Deny"}
                        </SecondaryButton>
                    </form>
                </div>
            </FormContainer>
        </Master>
    );
}
