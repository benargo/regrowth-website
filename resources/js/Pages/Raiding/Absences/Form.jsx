import { useMemo } from "react";
import { router, useForm, usePage } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import SectionNav from "@/Components/Navigation/SectionNav";
import PageContainer from "@/Components/PageContainer";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Icon from "@/Components/FontAwesome/Icon";
import EmptyState from "@/Components/EmptyState";
import UserSearch from "@/Components/Discord/UserSearch";
import CharacterSearch from "@/Components/Characters/CharacterSearch";
import { FormRow, FormSection, OptionSelect } from "@/Components/FormControls";
import FormPageLayout, { FormPageSection } from "@/Components/FormPage";
import AbsenceForm, { ABSENCE_SECTIONS } from "@/Components/PlannedAbsences/AbsenceForm";
import AbsenceDetailsFields from "@/Components/PlannedAbsences/AbsenceDetailsFields";
import normaliseCharacterName from "@/Helpers/NormaliseCharacterName";
import useDiscordMemberResolution from "@/Hooks/useDiscordMemberResolution";

/**
 * Find the one main whose name matches the Discord nickname, either its first
 * word or the whole of it. Returns null when none or several match.
 */
function matchNickname(characters, nickname) {
    const candidates = new Set([nickname.trim().split(/\s+/)[0], nickname.trim()].map(normaliseCharacterName));
    const matches = characters.filter((character) => candidates.has(normaliseCharacterName(character.name)));

    return matches.length === 1 ? matches[0] : null;
}

function OfficerAbsenceForm({ gameVersion, gameVersions, characters, plannedAbsence, action, reasons }) {
    const isEditing = plannedAbsence !== null;
    const originalCharacterId = plannedAbsence?.character?.id ?? null;

    const { data, setData, post, patch, transform, processing, errors, clearErrors, setError } = useForm({
        game_version: gameVersion?.id ?? null,
        character: originalCharacterId,
        user: plannedAbsence?.user?.id ?? null,
        start_date: plannedAbsence?.start_date ?? "",
        end_date: plannedAbsence?.end_date ?? "",
        reason: plannedAbsence?.reason?.key ?? "",
        notes: plannedAbsence?.notes ?? "",
    });
    const { resolve, isResolving, resolutionError } = useDiscordMemberResolution();

    // A legacy absence may belong to a character that is no longer a main; keep it selectable as the current value.
    const selectableCharacters = useMemo(() => {
        const current = plannedAbsence?.character;

        if (!current || characters.some((character) => character.id === current.id)) {
            return characters;
        }

        return [current, ...characters];
    }, [characters, plannedAbsence]);

    function handleUserSelect(member) {
        setData("user", member?.id ?? null);
        resolve(member ?? null);

        if (!member?.nickname || data.character) {
            return;
        }

        const matched = matchNickname(characters, member.nickname);

        if (matched) {
            setData((previous) => ({ ...previous, user: member.id, character: matched.id }));
        }
    }

    function selectGameVersion(slug) {
        const version = gameVersions.find((candidate) => candidate.slug === slug);

        if (!version || version.id === data.game_version) {
            return;
        }

        setData((previous) => ({ ...previous, game_version: version.id, character: null }));
        router.reload({ only: ["characters", "game_version"], data: { game_version: slug } });
    }

    function submit(visitOptions) {
        transform(({ game_version, character, user, ...rest }) => ({
            ...rest,
            ...(user ? { user } : {}),
            ...(!isEditing || character !== originalCharacterId ? { game_version, character } : {}),
        }));

        if (isEditing) {
            patch(action, visitOptions);
        } else {
            post(action, visitOptions);
        }
    }

    return (
        <AbsenceForm
            data={data}
            setError={setError}
            errors={errors}
            processing={processing || isResolving}
            onSubmit={submit}
            submitLabel={isEditing ? "Save changes" : "Log absence"}
            cancelHref={route("raiding.absences.index")}
        >
            <FormSection id="whos-away" legend="Who's away">
                <FormRow
                    htmlFor="user"
                    label="Discord user"
                    hint="Picking someone also picks their main, if their nickname matches one."
                    error={resolutionError ?? errors.user}
                >
                    <UserSearch
                        id="user"
                        value={data.user}
                        onSelect={handleUserSelect}
                        invalid={Boolean(resolutionError ?? errors.user)}
                    />
                </FormRow>

                {gameVersions && (
                    <FormRow htmlFor="game_version" label="Game version" error={errors.game_version}>
                        <OptionSelect
                            name="game_version"
                            value={gameVersions.find((version) => version.id === data.game_version)?.slug ?? gameVersion.slug}
                            onChange={selectGameVersion}
                            options={gameVersions.map((version) => ({ value: version.slug, label: version.title }))}
                        />
                    </FormRow>
                )}

                <FormRow htmlFor="character" label="Character" required error={errors.character}>
                    <CharacterSearch
                        refreshPortrait
                        id="character"
                        characters={selectableCharacters}
                        value={data.character}
                        onChange={(id) => {
                            setData("character", id);
                            if (id) {
                                clearErrors("character");
                            }
                        }}
                        invalid={Boolean(errors.character)}
                        emptyMessage={`No mains on the ${gameVersion.title} roster match your search.`}
                    />
                </FormRow>
            </FormSection>

            <AbsenceDetailsFields data={data} setData={setData} errors={errors} clearErrors={clearErrors} reasons={reasons} canBackdate isOfficer />
        </AbsenceForm>
    );
}

export default function Form() {
    const {
        game_versions: gameVersionsResource = null,
        game_version: gameVersionResource = null,
        characters: charactersResource,
        planned_absence: plannedAbsenceResource = null,
        action,
        reasons = [],
    } = usePage().props;
    const gameVersions = gameVersionsResource?.data ?? null;
    const gameVersion = gameVersionResource?.data ?? null;
    const characters = charactersResource?.data;
    const plannedAbsence = plannedAbsenceResource?.data ?? null;

    const pageTitle = plannedAbsence ? "Edit Planned Absence" : "Log Planned Absence";

    return (
        <Master title={pageTitle}>
            <SharedHeader title={pageTitle} backgroundClass="bg-illidan" />
            <SectionNav section="raiding" />

            <ToolNav>
                <ToolNavLink href={route("raiding.absences.index")}>
                    <Icon icon="arrow-left" style="solid" className="mr-2" />
                    <span>Back to planned absences</span>
                </ToolNavLink>
            </ToolNav>

            <PageContainer>
                {gameVersion ? (
                    <FormPageLayout sections={ABSENCE_SECTIONS}>
                        <FormPageSection>
                            <OfficerAbsenceForm
                                gameVersion={gameVersion}
                                gameVersions={gameVersions}
                                characters={characters}
                                plannedAbsence={plannedAbsence}
                                action={action}
                                reasons={reasons}
                            />
                        </FormPageSection>
                    </FormPageLayout>
                ) : (
                    <EmptyState
                        icon="calendar-times"
                        message="There's no released game version to log an absence against."
                    />
                )}
            </PageContainer>
        </Master>
    );
}
