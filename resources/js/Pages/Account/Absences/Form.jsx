import { useEffect } from "react";
import { Deferred, useForm, usePage } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Icon from "@/Components/FontAwesome/Icon";
import Alert from "@/Components/Alert";
import EmptyState from "@/Components/EmptyState";
import BattleNetLoginButton from "@/Components/Blizzard/BattleNetLoginButton";
import GameVersionSelector from "@/Components/GameVersions/GameVersionSelector";
import CharacterPortrait from "@/Components/Characters/CharacterPortrait";
import CharacterSearch from "@/Components/Characters/CharacterSearch";
import ClassPill from "@/Components/Characters/ClassPill";
import { FormRow, FormSection } from "@/Components/FormControls";
import FormPageLayout, { FormPageSection } from "@/Components/FormPage";
import AbsenceForm, { ABSENCE_SECTIONS } from "@/Components/PlannedAbsences/AbsenceForm";
import AbsenceDetailsFields from "@/Components/PlannedAbsences/AbsenceDetailsFields";
import { usePermission } from "@/Hooks/usePermission";

const TICKET_URL = "https://discord.com/channels/829020506907869214/1011331714376794233";

function ConnectBattleNet({ message }) {
    return (
        <div className="flex flex-col items-start gap-3">
            <p className="text-secondary-300 text-sm">{message}</p>
            <BattleNetLoginButton>Connect Battle.net</BattleNetLoginButton>
        </div>
    );
}

function CharacterPickerSkeleton() {
    return (
        <div className="animate-pulse" aria-hidden="true">
            <div className="border-ink-600 bg-ground-800 h-10.5 rounded border" />
        </div>
    );
}

function AccountCharacterPicker({ gameVersion, value, onChange, invalid }) {
    const { battle_net: battleNet, account_characters: accountCharacters } = usePage().props;
    const characters = accountCharacters?.characters?.data ?? [];
    const onlyCharacterId = characters.length === 1 ? characters[0].id : null;

    useEffect(() => {
        if (onlyCharacterId && value === null) {
            onChange(onlyCharacterId);
        }
    }, [onlyCharacterId]);

    if (!battleNet.connected) {
        return (
            <ConnectBattleNet message="Connect your Battle.net account to choose one of your characters. We only read your character list; nothing is stored." />
        );
    }

    return (
        <Deferred data="account_characters" fallback={<CharacterPickerSkeleton />}>
            {accountCharacters?.error?.code === "reconnect" ? (
                <ConnectBattleNet message={accountCharacters.error.message} />
            ) : accountCharacters?.error?.code === "unavailable" ? (
                <Alert type="warning">{accountCharacters.error.message}</Alert>
            ) : (
                <div className="flex flex-col gap-2">
                    {accountCharacters?.mains_only === false && (
                        <p className="text-secondary-300 text-sm">
                            None of your characters is marked as a main on the roster yet. Pick the one you raid on.
                        </p>
                    )}
                    <CharacterSearch
                        refreshPortrait
                        id="character"
                        characters={characters}
                        disabled={onlyCharacterId !== null}
                        value={value}
                        onChange={onChange}
                        invalid={invalid}
                        emptyMessage={`None of the characters on your Battle.net account are on the ${gameVersion.title} roster.`}
                    />
                </div>
            )}
        </Deferred>
    );
}

function AccountAbsenceForm({ gameVersion, plannedAbsence, action, canBackdate, reasons }) {
    const isEditing = plannedAbsence !== null;

    const { data, setData, post, patch, transform, processing, errors, clearErrors, setError } = useForm({
        game_version: gameVersion?.id ?? null,
        character: null,
        start_date: plannedAbsence?.start_date ?? "",
        end_date: plannedAbsence?.end_date ?? "",
        reason: plannedAbsence?.reason?.key ?? "",
        notes: plannedAbsence?.notes ?? "",
    });

    function submit(visitOptions) {
        // An absence's character can't change once it's logged.
        transform(({ game_version, character, ...rest }) => (isEditing ? rest : { ...rest, game_version, character }));

        if (isEditing) {
            patch(action, visitOptions);
        } else {
            post(action, visitOptions);
        }
    }

    const character = plannedAbsence?.character;

    return (
        <AbsenceForm
            data={data}
            setError={setError}
            errors={errors}
            processing={processing}
            onSubmit={submit}
            submitLabel={isEditing ? "Save changes" : "Log absence"}
            cancelHref={route("account.index")}
        >
            <FormSection id="whos-away" legend="Who's away">
                {isEditing ? (
                    <div className="flex items-center gap-3">
                        {character ? (
                            <>
                                <CharacterPortrait character={character} className="size-10" />
                                <span className="text-white">{character.name}</span>
                                <ClassPill playableClass={character.playable_class} />
                            </>
                        ) : (
                            <span className="text-secondary-300">Unknown character</span>
                        )}
                    </div>
                ) : (
                    <FormRow htmlFor="character" label="Character" required error={errors.character}>
                        <AccountCharacterPicker
                            gameVersion={gameVersion}
                            value={data.character}
                            onChange={(id) => {
                                setData("character", id);
                                if (id) {
                                    clearErrors("character");
                                }
                            }}
                            invalid={Boolean(errors.character)}
                        />
                    </FormRow>
                )}
            </FormSection>

            <AbsenceDetailsFields
                data={data}
                setData={setData}
                errors={errors}
                clearErrors={clearErrors}
                canBackdate={canBackdate}
                reasons={reasons}
            />
        </AbsenceForm>
    );
}

export default function Form() {
    const {
        game_versions: gameVersionsResource = null,
        game_version: gameVersionResource = null,
        planned_absence: plannedAbsenceResource = null,
        action,
        reasons = [],
    } = usePage().props;
    const gameVersions = gameVersionsResource?.data ?? null;
    const gameVersion = gameVersionResource?.data ?? null;
    const plannedAbsence = plannedAbsenceResource?.data ?? null;

    // Editing an absence takes the server's verdict; logging a new one falls back to the role permission.
    const hasBackdatePermission = usePermission("backdate-planned-absences");
    const canBackdate = plannedAbsenceResource
        ? Boolean(plannedAbsenceResource.permissions?.backdate)
        : hasBackdatePermission;
    const pageTitle = plannedAbsence ? "Edit Planned Absence" : "Log Planned Absence";

    const ticketNote = (
        <>
            Please{" "}
            <a href={TICKET_URL} className="underline hover:text-white">
                create a ticket on Discord
            </a>{" "}
            if you need to record an absence that started in the past, or your character isn't listed.
        </>
    );

    return (
        <Master title={pageTitle}>
            <SharedHeader title={pageTitle} backgroundClass="bg-illidan" />

            <ToolNav>
                <ToolNavLink href={route("account.index")}>
                    <Icon icon="arrow-left" style="solid" className="mr-2" />
                    <span>Back to your account</span>
                </ToolNavLink>
            </ToolNav>

            <PageContainer>
                {!plannedAbsence && gameVersions && (
                    <div className="mb-10">
                        <GameVersionSelector gameVersions={gameVersions} currentGameVersion={gameVersion} />
                    </div>
                )}

                {plannedAbsence || gameVersion ? (
                    <FormPageLayout sections={ABSENCE_SECTIONS} note={canBackdate ? null : ticketNote}>
                        <FormPageSection>
                            <AccountAbsenceForm
                                key={gameVersion?.slug ?? plannedAbsence.id}
                                gameVersion={gameVersion}
                                plannedAbsence={plannedAbsence}
                                action={action}
                                canBackdate={canBackdate}
                                reasons={reasons}
                            />
                        </FormPageSection>
                    </FormPageLayout>
                ) : (
                    <EmptyState
                        icon="calendar-times"
                        message="There's no guild roster to log an absence against yet."
                    />
                )}
            </PageContainer>
        </Master>
    );
}
